<?php
require_once __DIR__.'/_bootstrap.php';$requestId=UthengaTieObservability::requestId();try{[$user,$service,$requestId]=accommodation_v2_context();if($_SERVER['REQUEST_METHOD']==='GET')accommodation_v2_respond($requestId,'inventory',$service->rooms(accommodation_v2_property(),$user['id']));$input=accommodation_v2_write('accommodation_room_write',$requestId);$propertyId=accommodation_v2_property($input);$action=strtolower((string)($input['action']??''));$result=match($action){
    'save_room_type'=>$service->saveRoomType($propertyId,$user['id'],$input,$requestId),
    'update_room_type'=>$service->updateRoomType($propertyId,$user['id'],$input,$requestId),
    'deactivate_room_type'=>$service->deactivateRoomType($propertyId,$user['id'],$input,$requestId),
    'save_unit'=>$service->saveUnit($propertyId,$user['id'],$input,$requestId),
    'update_unit'=>$service->updateUnit($propertyId,$user['id'],$input,$requestId),
    'deactivate_unit'=>$service->deactivateUnit($propertyId,$user['id'],$input,$requestId),
    'save_rate_plan'=>$service->saveRatePlan($propertyId,$user['id'],$input,$requestId),
    'update_rate_plan'=>$service->updateRatePlan($propertyId,$user['id'],$input,$requestId),
    'deactivate_rate_plan'=>$service->deactivateRatePlan($propertyId,$user['id'],$input,$requestId),
    'save_cancellation_policy'=>$service->saveCancellationPolicy($propertyId,$user['id'],$input,$requestId),
    'update_cancellation_policy'=>$service->updateCancellationPolicy($propertyId,$user['id'],$input,$requestId),
    'deactivate_cancellation_policy'=>$service->deactivateCancellationPolicy($propertyId,$user['id'],$input,$requestId),
    default=>throw UthengaTieErrors::validation(['action'=>'Unsupported room inventory action.'])};accommodation_v2_respond($requestId,'result',$result);}catch(Throwable $e){UthengaTieApi::handleError($e,$requestId);}
