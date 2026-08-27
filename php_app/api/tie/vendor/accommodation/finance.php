<?php
require_once __DIR__.'/_bootstrap.php';$requestId=UthengaTieObservability::requestId();
try{[$user,$unused,$requestId]=accommodation_v2_context();global $pdo;$service=new UthengaAccommodationRefundService($pdo);
    if($_SERVER['REQUEST_METHOD']==='GET')accommodation_v2_respond($requestId,'finance',$service->listing(accommodation_v2_property(),$user['id']));
    $input=accommodation_v2_write('accommodation_refund_write',$requestId);$propertyId=accommodation_v2_property($input);accommodation_v2_respond($requestId,'refund',$service->mutate($propertyId,$user['id'],$input,$requestId));
}catch(Throwable $error){UthengaTieApi::handleError($error,$requestId);}
