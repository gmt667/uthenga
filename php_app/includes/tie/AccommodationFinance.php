<?php
/** Governed accommodation refund review and externally evidenced execution. */
final class UthengaAccommodationRefundService
{
    public function __construct(private PDO $db) {}

    public function listing(string $propertyId,string $actorId): array
    {
        $this->authorize($propertyId,$actorId,false);
        $stmt=$this->db->prepare("SELECT rr.*,r.reservation_code,r.booking_id,r.guest_name,r.amount_paid,r.payment_status
            FROM tie_accommodation_refund_requests rr
            INNER JOIN tie_accommodation_reservations r ON r.id=rr.reservation_id
            WHERE rr.property_id=? ORDER BY rr.created_at DESC LIMIT 200");$stmt->execute([$propertyId]);
        $events=$this->db->prepare("SELECT e.* FROM tie_accommodation_refund_events e INNER JOIN tie_accommodation_refund_requests rr ON rr.id=e.refund_request_id WHERE rr.property_id=? ORDER BY e.created_at DESC LIMIT 300");$events->execute([$propertyId]);
        return ['schema_version'=>'tie-accommodation-refunds/v1','requests'=>$stmt->fetchAll(),'events'=>$events->fetchAll(),'provider_execution'=>['standard_checkout'=>'manual_provider_evidence_required','direct_card'=>'not_enabled']];
    }

    public function mutate(string $propertyId,string $actorId,array $input,string $correlationId): array
    {
        $this->authorize($propertyId,$actorId,true);$id=UthengaAccommodationContracts::id($input['refund_request_id']??'','refund_request_id');$operation=strtoupper(trim((string)($input['operation']??'')));
        if(!in_array($operation,['APPROVE','REJECT','RECORD_EXTERNAL_EXECUTION'],true))throw UthengaTieErrors::validation(['operation'=>'Use approve, reject, or record_external_execution.']);
        $this->db->beginTransaction();
        try{
            $stmt=$this->db->prepare('SELECT rr.*,r.booking_id,r.amount_paid,r.payment_status,r.reservation_code FROM tie_accommodation_refund_requests rr INNER JOIN tie_accommodation_reservations r ON r.id=rr.reservation_id WHERE rr.id=? AND rr.property_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$id,$propertyId]);$before=$stmt->fetch();if(!is_array($before))throw UthengaTieErrors::authorization();
            $version=UthengaAccommodationContracts::integer($input['version']??0,1,PHP_INT_MAX,'version');if((int)$before['version']!==$version)throw UthengaTieErrors::validation(['version'=>'This refund changed. Refresh before continuing.']);
            $note=UthengaAccommodationContracts::text($input['note']??'',1000,$operation!=='APPROVE');$from=(string)$before['status'];$to=$from;$providerReference=null;
            if($operation==='APPROVE'){
                if($from!=='PENDING')throw UthengaTieErrors::validation(['refund'=>'Only a pending refund can be approved.']);
                if($before['risk_level']==='EXCEPTION'&&!$this->isAdmin($actorId))throw UthengaTieErrors::authorization();
                $to='APPROVED';$this->db->prepare("UPDATE tie_accommodation_refund_requests SET status='APPROVED',reviewed_by=?,review_note=?,approved_at=UTC_TIMESTAMP(),version=version+1 WHERE id=?")->execute([$actorId,$note?:null,$id]);
            }elseif($operation==='REJECT'){
                if(!in_array($from,['PENDING','APPROVED','FAILED','AWAITING_PROVIDER'],true))throw UthengaTieErrors::validation(['refund'=>'This refund can no longer be rejected.']);
                $to='REJECTED';$this->db->prepare("UPDATE tie_accommodation_refund_requests SET status='REJECTED',reviewed_by=?,review_note=?,version=version+1 WHERE id=?")->execute([$actorId,$note,$id]);
            }else{
                if(!in_array($from,['APPROVED','AWAITING_PROVIDER','FAILED'],true))throw UthengaTieErrors::validation(['refund'=>'Approve the refund before recording provider execution.']);
                $providerReference=UthengaAccommodationContracts::text($input['provider_refund_reference']??'',160,true);$key=UthengaAccommodationContracts::text($input['idempotency_key']??'',100,true);if(strlen($key)<16)throw UthengaTieErrors::validation(['idempotency_key'=>'Use a unique key of at least 16 characters.']);
                $duplicate=$this->db->prepare('SELECT id FROM tie_accommodation_refund_requests WHERE execution_idempotency_key=? AND id<>? LIMIT 1');$duplicate->execute([$key,$id]);if($duplicate->fetchColumn())throw UthengaTieErrors::validation(['idempotency_key'=>'This execution key has already been used.']);
                $transaction=$this->successfulTransaction((string)($before['booking_id']??''));if($transaction===null)throw UthengaTieErrors::validation(['refund'=>'No successful booking transaction exists to reconcile this refund.']);
                $hash=hash('sha256',json_encode(['refund_request_id'=>$id,'payment_transaction_id'=>$transaction['id'],'provider_reference'=>$providerReference,'amount'=>(float)$before['amount'],'currency'=>$before['currency']],JSON_UNESCAPED_SLASHES));
                $refundTransaction='TXN-'.strtoupper(bin2hex(random_bytes(6)));$receipt='RFD-'.strtoupper(bin2hex(random_bytes(5)));
                $this->db->prepare("INSERT INTO transactions (id,transaction_reference,booking_id,customer_id,user_id,customer_name,amount,gateway,gateway_ref,gateway_name,transaction_type,status,receipt_number,vendor_id,metadata,transaction_date,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())")
                    ->execute([$refundTransaction,$providerReference,$transaction['booking_id'],$transaction['customer_id'],$transaction['user_id'],$transaction['customer_name'],(float)$before['amount'],$transaction['gateway'],$providerReference,$transaction['gateway_name']?:$transaction['gateway'],'refund','success',$receipt,$transaction['vendor_id'],json_encode(['source'=>'accommodation_refund_reconciliation','refund_request_id'=>$id,'original_transaction_id'=>$transaction['id'],'evidence_hash'=>$hash],JSON_UNESCAPED_SLASHES)]);
                $remaining=max(0,round((float)$before['amount_paid']-(float)$before['amount'],2));$paymentStatus=$remaining<=0?'Refunded':'Partially Refunded';
                $this->db->prepare('UPDATE tie_accommodation_reservations SET amount_paid=?,payment_status=?,version=version+1 WHERE id=?')->execute([$remaining,$paymentStatus,$before['reservation_id']]);
                if(!empty($before['booking_id']))$this->db->prepare('UPDATE bookings SET payment_status=?,booking_status=CASE WHEN ?=\'Refunded\' THEN \'refunded\' ELSE booking_status END,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$paymentStatus,$paymentStatus,$before['booking_id']]);
                $to='EXECUTED';$this->db->prepare("UPDATE tie_accommodation_refund_requests SET status='EXECUTED',provider_name=?,provider_refund_reference=?,execution_idempotency_key=?,provider_response_hash=?,executed_at=UTC_TIMESTAMP(),version=version+1 WHERE id=?")->execute([$transaction['gateway_name']?:$transaction['gateway'],$providerReference,$key,$hash,$id]);
            }
            $this->event($id,$actorId,strtolower($operation),$from,$to,$correlationId,$note,$providerReference===null?null:hash('sha256',$providerReference));$this->db->commit();return $this->one($propertyId,$id);
        }catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
    }

    private function one(string $propertyId,string $id): array{$s=$this->db->prepare('SELECT * FROM tie_accommodation_refund_requests WHERE id=? AND property_id=?');$s->execute([$id,$propertyId]);return $s->fetch()?:[];}
    private function successfulTransaction(string $bookingId): ?array{if($bookingId==='')return null;$s=$this->db->prepare("SELECT * FROM transactions WHERE booking_id=? AND LOWER(status)='success' AND COALESCE(transaction_type,'booking_payment')<>'refund' ORDER BY created_at DESC LIMIT 1 FOR UPDATE");$s->execute([$bookingId]);$r=$s->fetch();return is_array($r)?$r:null;}
    private function event(string $id,string $actor,string $action,string $from,string $to,string $correlation,string $note,?string $responseHash):void{$this->db->prepare('INSERT INTO tie_accommodation_refund_events (refund_request_id,actor_id,action_key,from_status,to_status,provider_name,provider_response_hash,note,correlation_id) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$id,$actor,$action,$from,$to,null,$responseHash,$note?:null,$correlation]);}
    private function authorize(string $propertyId,string $actorId,bool $write):void
    {
        if($this->isAdmin($actorId))return;$s=$this->db->prepare("SELECT CASE WHEN p.vendor_id=? THEN 'OWNER' ELSE m.role_key END role_key FROM tie_accommodation_properties p LEFT JOIN tie_accommodation_staff_memberships m ON m.property_id=p.id AND m.user_id=? AND m.status='ACTIVE' WHERE p.id=? AND (p.vendor_id=? OR m.id IS NOT NULL) LIMIT 1");$s->execute([$actorId,$actorId,$propertyId,$actorId]);$role=(string)$s->fetchColumn();if($role===''||!UthengaAccommodationPermissions::allows($role,$write?'refund.request':'finance.read'))throw UthengaTieErrors::authorization();if($write&&!in_array($role,['OWNER','GENERAL_MANAGER','FINANCE'],true))throw UthengaTieErrors::authorization();
    }
    private function isAdmin(string $actorId):bool{$s=$this->db->prepare('SELECT role FROM users WHERE id=? LIMIT 1');$s->execute([$actorId]);return in_array((string)$s->fetchColumn(),ADMIN_ROLES,true);}
}
