<?php
/** Phase D2.4 shared callback primitives. No endpoint enables this implicitly. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/financial_controls.php';

final class UthengaPaychanguCallbackAdapter {
    public function authenticate(string $raw, string $signature, string $secret): array {
        if ($secret === '' || $signature === '' || !preg_match('/^[a-f0-9]{64}$/i', $signature)) throw new InvalidArgumentException('unauthenticated');
        if (!hash_equals(hash_hmac('sha256', $raw, $secret), strtolower($signature))) throw new InvalidArgumentException('unauthenticated');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) throw new InvalidArgumentException('malformed_payload');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $reference = trim((string)($data['tx_ref'] ?? $payload['tx_ref'] ?? ''));
        $eventId = trim((string)($data['charge_id'] ?? $payload['charge_id'] ?? $reference));
        $currency = strtoupper(trim((string)($data['currency'] ?? $payload['currency'] ?? '')));
        $amount = (string)($data['amount'] ?? $payload['amount'] ?? '');
        $status = strtolower(trim((string)($data['status'] ?? $payload['status'] ?? '')));
        if ($reference === '' || $eventId === '' || !in_array($currency, ['MWK','USD'], true)) throw new InvalidArgumentException('invalid_event');
        $minor = UthengaFinancialState::mwkToMinor($amount);
        $state = match ($status) { 'success' => 'SUCCESSFUL', 'pending' => 'PENDING', 'processing' => 'PROCESSING', 'failed','declined','error' => 'FAILED', 'cancelled','canceled' => 'CANCELLED', 'expired' => 'EXPIRED', default => 'UNKNOWN' };
        return ['provider'=>'paychangu','event_id'=>$eventId,'reference'=>$reference,'event_type'=>(string)($payload['event_type'] ?? 'payment'),'state'=>$state,'raw_status'=>$status,'currency'=>$currency,'amount_minor'=>$minor,'digest'=>hash('sha256',$raw)];
    }
}

final class UthengaProviderCallbackReceipts {
    public static function reserve(array $event): array {
        if (!uthenga_table_exists('uthenga_provider_callback_receipts') || !uthenga_table_exists('uthenga_provider_callback_processing') || !uthenga_table_exists('uthenga_provider_callback_commits')) throw new RuntimeException('callback_controls_unavailable');
        global $pdo; $pdo->beginTransaction();
        try {
            $stmt=$pdo->prepare('SELECT * FROM uthenga_provider_callback_receipts WHERE provider=? AND event_identity=? FOR UPDATE');$stmt->execute([$event['provider'],$event['event_id']]);$row=$stmt->fetch();
            if ($row) { if (!hash_equals((string)$row['payload_digest'], $event['digest'])) throw new RuntimeException('conflicting_replay'); $pdo->commit(); return ['duplicate'=>true,'receipt'=>$row]; }
            $pdo->prepare("INSERT INTO uthenga_provider_callback_receipts (provider,event_identity,event_type,payment_reference,payload_digest,processing_status) VALUES (?,?,?,?,?,'AUTHENTICATED')")->execute([$event['provider'],$event['event_id'],$event['event_type'],$event['reference'],$event['digest']]);
            $id=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO uthenga_provider_callback_processing (receipt_id,attempt_count,last_attempt_at,safe_metadata_json) VALUES (?,?,NOW(),?)')->execute([$id,1,json_encode(['raw_status'=>$event['raw_status'],'state'=>$event['state']],JSON_UNESCAPED_SLASHES)]);$pdo->commit();return ['duplicate'=>false,'receipt'=>['id'=>$id]];
        } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
    }
}
