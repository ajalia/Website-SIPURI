<?php
namespace App\Http\Controllers;

use App\Models\{House, HouseResidency, User};
use App\Services\AddressGuard;
use App\Support\HouseAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class RtResidentController extends Controller
{
    private function authorizeRecord(Request $request, HouseResidency $residency): void
    {
        abort_unless($request->user()->rt_id !== null && $residency->house->rt_id === $request->user()->rt_id, 403);
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['q'=>'nullable|string|max:100', 'status'=>'nullable|in:all,current,moved']);
        $q = $filters['q'] ?? '';
        $status = $filters['status'] ?? 'all';
        $records = HouseResidency::with(['house.rt', 'user'])
            ->whereHas('house', fn($query) => $query->where('rt_id', $request->user()->rt_id))
            ->when($status === 'current', fn($query) => $query->whereNull('moved_at'))
            ->when($status === 'moved', fn($query) => $query->whereNotNull('moved_at'))
            ->when($q !== '', fn($query) => $query->where(fn($sub) => $sub
                ->where('resident_name', 'like', '%'.$q.'%')->orWhere('block', 'like', '%'.$q.'%')
                ->orWhere('house_number', 'like', '%'.$q.'%')->orWhereHas('user', fn($u) => $u->where('username', 'like', '%'.$q.'%'))))
            ->orderBy('block')->orderBy('house_number')->orderByDesc('id')->paginate(15)->withQueryString();
        return view('rt.houses', compact('records', 'q', 'status'));
    }

    public function show(Request $request, HouseResidency $residency)
    {
        $this->authorizeRecord($request, $residency);
        $residency->load(['house.rt', 'user', 'recorder']);
        return view('rt.resident', compact('residency'));
    }

    private function activeRecord(Request $request, HouseResidency $residency): array
    {
        $record = HouseResidency::whereKey($residency->id)->lockForUpdate()->firstOrFail();
        $house = House::whereKey($record->house_id)->lockForUpdate()->firstOrFail();
        $this->authorizeRecord($request, $record);
        if ($record->moved_at || $house->current_user_id !== $record->user_id) {
            throw ValidationException::withMessages(['record'=>'Catatan sudah berubah atau sudah pindah. Muat ulang halaman.']);
        }
        return [$record, $house];
    }

    public function update(Request $request, HouseResidency $residency)
    {
        $this->authorizeRecord($request, $residency);
        foreach (['name', 'change_reason'] as $key) if(is_string($request->input($key))) $request->merge([$key=>trim($request->input($key))]);
        $request->merge(['block'=>HouseAddress::block($request->input('block')), 'house_number'=>HouseAddress::number($request->input('house_number'))]);
        $data = $request->validateWithBag('edit', [
            'name'=>'required|string|max:150', 'block'=>HouseAddress::blockRules(),
            'house_number'=>HouseAddress::numberRules(), 'change_reason'=>'required|string|max:2000',
        ], ['required'=>'Isian ini wajib diisi.', 'max'=>'Isian terlalu panjang.', 'block.regex'=>HouseAddress::BLOCK_HELP, 'house_number.regex'=>'Nomor rumah harus bilangan bulat positif.']);
        try {
            AddressGuard::transaction(function () use ($request, $residency, $data) {
                [$record, $house] = $this->activeRecord($request, $residency);
                AddressGuard::assertAvailable($data['block'], $data['house_number'], $record->user_id);
                $user = User::whereKey($record->user_id)->lockForUpdate()->firstOrFail();
                $before = $record->only(['house_id', 'resident_name', 'block', 'house_number']);
                $target = House::where('rt_id',$house->rt_id)->where('block',$data['block'])->where('house_number',$data['house_number'])->lockForUpdate()->first();
                if($target && $target->id !== $house->id){
                    throw ValidationException::withMessages(['block'=>'Alamat ini terkait catatan rumah lain. Koreksi tidak memindahkan akun ke rumah lain. Gunakan Catat Pindah dan hubungi pengurus untuk penataan hunian.']);
                }
                $record->fill(['resident_name'=>$data['name'], 'block'=>$data['block'], 'house_number'=>$data['house_number']])->save();
                $house->fill(['block'=>$data['block'], 'house_number'=>$data['house_number']])->save();
                $user->fill(['name'=>$data['name'], 'block'=>$data['block'], 'house_number'=>$data['house_number']])->save();
                DB::table('residency_changes')->insert([
                    'house_residency_id'=>$record->id, 'actor_id'=>$request->user()->id, 'reason'=>$data['change_reason'],
                    'before_data'=>json_encode($before, JSON_THROW_ON_ERROR),
                    'after_data'=>json_encode($record->only(['house_id', 'resident_name', 'block', 'house_number']), JSON_THROW_ON_ERROR),
                    'created_at'=>now(), 'updated_at'=>now(),
                ]);
            });
        } catch (ValidationException $e) {
            $e->errorBag = 'edit'; throw $e;
        } catch (QueryException $e) {
            if (in_array((string)$e->getCode(), ['23000','23505'], true)) throw ValidationException::withMessages(['block'=>HouseAddress::CONFLICT])->errorBag('edit');
            throw $e;
        }
        return redirect()->route('rt.houses.show', $residency)->with('success', 'Perubahan berhasil disimpan.');
    }

    public function move(Request $request, HouseResidency $residency)
    {
        $this->authorizeRecord($request, $residency);
        if (is_string($request->input('move_note'))) $request->merge(['move_note'=>trim($request->input('move_note'))]);
        $today = now('Asia/Jakarta')->toDateString();
        $data = $request->validateWithBag('move', ['moved_at'=>'nullable|date_format:Y-m-d|before_or_equal:'.$today, 'move_note'=>'required|string|max:2000'],
            ['move_note.required'=>'Alasan / keterangan kepindahan wajib diisi.', 'moved_at.before_or_equal'=>'Tanggal tidak boleh melebihi hari ini.']);
        try {
            AddressGuard::transaction(function () use ($request, $residency, $data, $today) {
                [$record, $house] = $this->activeRecord($request, $residency);
                $date = $data['moved_at'] ?? $today;
                if ($record->started_at && $date < $record->started_at->timezone('Asia/Jakarta')->toDateString()) {
                    throw ValidationException::withMessages(['moved_at'=>'Tanggal pindah tidak boleh sebelum awal pencatatan hunian.']);
                }
                $record->fill(['moved_at'=>$date, 'move_note'=>$data['move_note'], 'recorded_by'=>$request->user()->id])->save();
                $house->current_user_id = null;
                $house->save();
                // Account, approval status, transactions and billing rules remain unchanged.
            });
        } catch (ValidationException $e) { $e->errorBag = 'move'; throw $e; }
        return redirect()->route('rt.houses.show', $residency)->with('success', 'Kepindahan berhasil dicatat. Riwayat tetap tersimpan.');
    }
}
