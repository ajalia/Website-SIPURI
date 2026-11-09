<?php
namespace App\Services;
use App\Models\FinancialReport;
use App\Support\{Dues,HouseAddress};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

class CashBookService {
 // All cash writers (including payment acceptance) acquire this row FIRST.
 public function locked(callable $callback): mixed {
  return DB::transaction(function()use($callback){$book=DB::table('cash_books')->where('id',1)->lockForUpdate()->first();return $callback($book);},3);
 }
 public function configure(array $data,int $actor): void {
  $this->locked(function($book)use($data,$actor){
   if($book->starts_on!==null)throw ValidationException::withMessages(['opening'=>'Saldo pembukaan sudah disimpan dan dikunci. Koreksi memerlukan pemeriksaan pengurus, bukan formulir biasa.']);
   $earliest=collect([DB::table('dues_income_entries')->min('transaction_date'),DB::table('cash_transactions')->min('transaction_date')])->filter()->min();
   if($earliest&&$data['starts_on']>$earliest)throw ValidationException::withMessages(['starts_on'=>'Tanggal mulai harus pada atau sebelum transaksi paling awal: '.$earliest.'.']);
   DB::table('cash_books')->where('id',1)->update(['starts_on'=>$data['starts_on'],'opening_balance'=>(int)$data['opening_balance'],'configured_by'=>$actor,'configured_at'=>now()]);
  });
 }
 public function evidence(string $kind,int $id): ?array {
  foreach(DB::table('cash_proofs')->where('source_kind',$kind)->where('source_id',$id)->orderByDesc('id')->get() as $extra){
   if(Storage::disk('cash_proofs')->exists($extra->path))return ['disk'=>'cash_proofs','path'=>$extra->path,'mime'=>$extra->mime,'size'=>$extra->file_size];
  }
  if($kind==='dues'){
   $proof=DB::table('dues_income_entries as d')->join('payment_submissions as p','p.id','=','d.payment_submission_id')->where('d.id',$id)->select('p.proof_path','p.mime','p.file_size')->first();
   if($proof&&Storage::disk('payment_proofs')->exists($proof->proof_path))return ['disk'=>'payment_proofs','path'=>$proof->proof_path,'mime'=>$proof->mime,'size'=>$proof->file_size];
  }
  return null;
 }
 public function sourceExists(string $kind,int $id): bool {return DB::table($kind==='dues'?'dues_income_entries':'cash_transactions')->where('id',$id)->exists();}
 public function statement(int $year,int $month,$book): array {
  $start=CarbonImmutable::create($year,$month,1,0,0,0,'Asia/Jakarta')->toDateString();$end=CarbonImmutable::parse($start)->endOfMonth()->toDateString();
  $rows=[];$problems=[];$identity=[];
  $dues=DB::table('dues_income_entries as d')->leftJoin('payment_submissions as p','p.id','=','d.payment_submission_id')->leftJoin('invoices as i','i.id','=','d.invoice_id')->leftJoin('dues_periods as dp','dp.id','=','i.dues_period_id')->where('d.transaction_date','<=',$end)->orderBy('d.id')->select('d.*','p.status as payment_status','p.invoice_id as payment_invoice','p.amount as payment_amount','p.paid_on','i.status as invoice_status','i.amount as invoice_amount','i.resident_name','i.block','i.house_number','dp.year','dp.month')->get();
  foreach($dues as $d){
   if($d->payment_status!=='accepted'||$d->invoice_status!=='paid'||(int)$d->payment_invoice!==(int)$d->invoice_id||(int)$d->amount!==(int)$d->payment_amount||(int)$d->amount!==(int)$d->invoice_amount||$d->paid_on!==$d->transaction_date||!$d->year||!$d->month)$problems[]='Pemasukan iuran #'.$d->id.' memiliki hubungan/status/nominal/tanggal yang perlu diperiksa.';
   $proof=$this->evidence('dues',$d->id);
   $row=['kind'=>'dues','id'=>$d->id,'date'=>$d->transaction_date,'description'=>'Iuran Bulanan — '.(Dues::months()[$d->month]??'?').' '.$d->year.' — '.$d->resident_name.' — '.HouseAddress::display($d->block,$d->house_number),'income'=>(int)$d->amount,'expense'=>0,'proof'=>(bool)$proof];
   $rows[]=$row;$identity[]=(array)$d;
  }
  foreach(DB::table('cash_transactions')->where('transaction_date','<=',$end)->orderBy('id')->get() as $t){
   if(!in_array($t->kind,['income','expense'],true)||(int)$t->amount<1)$problems[]='Transaksi kas #'.$t->id.' memiliki jenis/nominal tidak valid.';
   $rows[]=['kind'=>'manual','id'=>$t->id,'date'=>$t->transaction_date,'description'=>$t->description,'income'=>$t->kind==='income'?(int)$t->amount:0,'expense'=>$t->kind==='expense'?(int)$t->amount:0,'proof'=>(bool)$this->evidence('manual',$t->id)];$identity[]=(array)$t;
  }
  $configured=$book&&$book->starts_on!==null;
  $balanceAvailable=$configured&&$end>=$book->starts_on;
  if($configured&&$end<$book->starts_on)$problems[]='Periode ini sebelum tanggal mulai pembukuan; saldo periode belum tersedia.';
  if($configured&&collect($rows)->contains(fn($r)=>$r['date']<$book->starts_on))$problems[]='Ada transaksi sebelum tanggal mulai pembukuan. Periksa saldo pembukaan bersama pengurus; nominal tidak diubah otomatis.';
  $summary=['opening'=>$configured?(int)$book->opening_balance:0,'income'=>0,'expense'=>0,'closing'=>0];$period=[];
  foreach($rows as $r){
   if($r['date']<$start)$summary['opening']=$this->add($summary['opening'],$r['income']-$r['expense']);
   else {$period[]=$r;$summary['income']=$this->add($summary['income'],$r['income']);$summary['expense']=$this->add($summary['expense'],$r['expense']);}
  }
  $summary['closing']=$this->add($this->add($summary['opening'],$summary['income']),-$summary['expense']);
  usort($period,fn($a,$b)=>[$a['date'],$a['kind'],$a['id']]<=>[$b['date'],$b['kind'],$b['id']]);
  $missing=collect($rows)->filter(fn($r)=>!$r['proof'])->values()->all();
  if($missing)$problems[]='Bukti belum tersedia untuk '.count($missing).' transaksi sampai akhir periode. Lengkapi melalui Bendahara sebelum penerbitan.';
  $ready=$configured&&$end>=$book->starts_on&&count($period)>0&&count($problems)===0;
  $fingerprint=hash('sha256',json_encode([$book,$identity,$rows,$summary],JSON_THROW_ON_ERROR));
  return compact('summary','period','configured','balanceAvailable','ready','problems','missing','fingerprint','start','end');
 }
 private function add(int $a,int $b): int {$result=$a+$b;if(!is_int($result))throw ValidationException::withMessages(['cash'=>'Jumlah rupiah melampaui batas perhitungan. Minta pengurus memeriksa data.']);return $result;}
 public function publicRows(array $statement,array $descriptions): array {
  $rows=[];$groups=[];
  foreach($statement['period'] as $r){
   if($r['kind']==='dues'){$groups[$r['date']]=$this->add($groups[$r['date']]??0,$r['income']);continue;}
   $text=trim($descriptions[$r['id']]??'');if($text===''||mb_strlen($text)>500)throw ValidationException::withMessages(['descriptions.'.$r['id']=>'Tinjau dan isi keterangan untuk laporan warga (maksimal 500 karakter).']);
   $rows[]=['date'=>$r['date'],'description'=>$text,'income'=>$r['income'],'expense'=>$r['expense']];
  }
  foreach($groups as $date=>$amount)$rows[]=['date'=>$date,'description'=>'Penerimaan iuran warga','income'=>$amount,'expense'=>0];
  usort($rows,fn($a,$b)=>[$a['date'],$a['description']]<=>[$b['date'],$b['description']]);return $rows;
 }
 public function publish(array $preview,string $token,int $actor): FinancialReport {
  return $this->locked(function($book)use($preview,$token,$actor){
   $hash=hash('sha256',$token);
   if(FinancialReport::where('request_hash',$hash)->lockForUpdate()->exists())throw ValidationException::withMessages(['publication'=>'Permintaan penerbitan ini sudah diproses. Muat ulang halaman.']);
   $statement=$this->statement($preview['year'],$preview['month'],$book);
   if(!$statement['ready']||!hash_equals($preview['fingerprint'],$statement['fingerprint']))throw ValidationException::withMessages(['publication'=>'Sumber kas berubah atau belum siap. Tinjau ulang laporan sebelum menerbitkan.']);
   $latest=FinancialReport::where('year',$preview['year'])->where('month',$preview['month'])->orderByDesc('version')->lockForUpdate()->first();
   if($latest&&hash_equals($latest->source_fingerprint,$statement['fingerprint'])&&$latest->public_rows===$preview['rows'])throw ValidationException::withMessages(['publication'=>'Versi dengan sumber dan keterangan yang sama sudah diterbitkan.']);
   return FinancialReport::create(['year'=>$preview['year'],'month'=>$preview['month'],'version'=>($latest?->version??0)+1,'source_fingerprint'=>$statement['fingerprint'],'request_hash'=>$hash,'summary'=>$statement['summary'],'public_rows'=>$preview['rows'],'published_by'=>$actor,'published_at'=>now()]);
  });
 }
}
