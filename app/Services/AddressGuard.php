<?php
namespace App\Services;

use App\Models\{House, HouseResidency, User};
use App\Support\HouseAddress;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

final class AddressGuard
{
    /** Serialize every address/status writer across RW 07, including different RTs. */
    public static function transaction(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            $lock = DB::table('address_write_locks')->where('id', 1)->lockForUpdate()->first();
            if (!$lock) throw new \RuntimeException('Address write lock has not been initialized.');
            return $callback();
        }, 3);
    }

    /** Call only after acquiring the database mutex, before changing any address owner. */
    public static function assertAvailable(mixed $block, mixed $number, ?int $exceptUserId = null): void
    {
        Validator::make(['block'=>$block, 'house_number'=>$number], [
            'block'=>HouseAddress::blockRules(), 'house_number'=>HouseAddress::numberRules(),
        ], ['block.regex'=>HouseAddress::BLOCK_HELP, 'house_number.regex'=>'Nomor rumah harus bilangan bulat positif.'])->validate();

        $pending = User::where('role', 'warga')->where('registration_status', 'pending')
            ->when($exceptUserId !== null, fn($q)=>$q->whereKeyNot($exceptUserId))->get(['block','house_number']);
        $houses = House::whereNotNull('current_user_id')
            ->when($exceptUserId !== null, fn($q)=>$q->where('current_user_id','!=',$exceptUserId))->get(['block','house_number']);
        $current = HouseResidency::whereNull('moved_at')
            ->when($exceptUserId !== null, fn($q)=>$q->where('user_id','!=',$exceptUserId))->get(['block','house_number']);
        // Canonical comparison also protects legacy collisions deliberately left for RT review.
        foreach ([$pending, $houses, $current] as $rows) {
            foreach ($rows as $row) {
                if (HouseAddress::block($row->block)===$block && HouseAddress::number($row->house_number)===$number) {
                    throw ValidationException::withMessages(['block'=>HouseAddress::CONFLICT]);
                }
            }
        }
    }
}
