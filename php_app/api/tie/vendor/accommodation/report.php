<?php
require_once __DIR__.'/_bootstrap.php';
$requestId=UthengaTieObservability::requestId();
try{
    if($_SERVER['REQUEST_METHOD']!=='GET')throw UthengaTieErrors::validation(['method'=>'GET is required.']);
    [$user,$service,$requestId]=accommodation_v2_context();
    $from=(string)($_GET['from']??gmdate('Y-m-01'));
    $to=(string)($_GET['to']??gmdate('Y-m-d',strtotime('first day of next month')));
    accommodation_v2_respond($requestId,'report',$service->report(accommodation_v2_property(),$user['id'],$from,$to));
}catch(Throwable $error){UthengaTieApi::handleError($error,$requestId);}
