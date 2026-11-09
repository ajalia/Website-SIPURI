<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

final class AddressDataAudit
{
    public static function inspect(): array
    {
        $issues=[]; $candidates=[]; $houses=[]; $owners=[];
        foreach (['users','houses','house_residencies'] as $table) {
            $query=DB::table($table);
            if ($table==='users') $query->where('role','warga');
            foreach ($query->get() as $row) {
                $block=HouseAddress::block($row->block); $number=HouseAddress::number($row->house_number);
                $key=(string)$block.'|'.(string)$number;
                if (!HouseAddress::valid($block,$number)) {
                    $issues[]=['table'=>$table,'id'=>$row->id,'issue'=>'invalid address'];
                    continue;
                }
                if ($block!==$row->block || $number!==$row->house_number) $candidates[]=['table'=>$table,'id'=>$row->id,'key'=>$key,'block'=>$block,'house_number'=>$number];
                if ($table==='houses') $houses[$key][]=$row->id;
                $owner=match($table) {
                    'users'=>$row->registration_status==='pending'?$row->id:null,
                    'houses'=>$row->current_user_id,
                    'house_residencies'=>$row->moved_at===null?$row->user_id:null,
                };
                if ($owner) $owners[$key][(string)$owner]=true;
            }
        }
        $blocked=[];
        foreach ($houses as $key=>$ids) if(count($ids)>1) {
            $blocked[$key]=true;
            $issues[]=['address'=>$key,'house_ids'=>$ids,'issue'=>'multiple house records; no automatic merge'];
        }
        foreach ($owners as $key=>$ids) if(count($ids)>1) {
            $blocked[$key]=true;
            $issues[]=['address'=>$key,'user_ids'=>array_keys($ids),'issue'=>'active/pending address collision'];
        }
        return ['updates'=>array_values(array_filter($candidates,fn($row)=>!isset($blocked[$row['key']]))),
            'deferred'=>array_values(array_filter($candidates,fn($row)=>isset($blocked[$row['key']]))), 'issues'=>$issues];
    }
}
