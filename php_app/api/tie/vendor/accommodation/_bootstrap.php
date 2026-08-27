<?php
require_once __DIR__ . '/../../../../config.php';
require_once __DIR__ . '/../../../../db.php';
require_once __DIR__ . '/../../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Api.php';

function accommodation_v2_context(): array
{
    UthengaTieApi::requireFeature('accommodation_v2');
    $user=UthengaTieApi::requireAuthenticatedUser();global $pdo;if(!$pdo instanceof PDO)throw UthengaTieErrors::providerUnavailable('database');
    return [$user,new UthengaAccommodationService($pdo),UthengaTieObservability::requestId()];
}
function accommodation_v2_input(): array {return UthengaTieApi::input();}
function accommodation_v2_property(array $input=[]): string {return UthengaAccommodationContracts::id($input['property_id']??($_GET['property_id']??''),'property_id');}
function accommodation_v2_write(string $bucket,string $requestId): array {if($_SERVER['REQUEST_METHOD']!=='POST')throw UthengaTieErrors::validation(['method'=>'POST is required.']);UthengaTieApi::requireCsrf();UthengaTieApi::requireRateLimit($bucket,60,60,$requestId);return accommodation_v2_input();}
function accommodation_v2_respond(string $requestId,string $key,array $value): void {UthengaTieApi::respond(['success'=>true,'schema_version'=>'tie-accommodation-api/v2','request_id'=>$requestId,$key=>$value]);}
