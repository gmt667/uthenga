<?php
require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/../../../db.php';
require_once __DIR__.'/../../../includes/auth_check.php';
require_once __DIR__.'/../../../includes/financial_controls.php';
require_once __DIR__.'/../../../includes/tie/bootstrap.php';
require_once __DIR__.'/../../../includes/tie/Api.php';
$requestId=UthengaTieObservability::requestId();
try{
    if($_SERVER['REQUEST_METHOD']!=='POST')throw UthengaTieErrors::validation(['method'=>'POST is required.']);
    UthengaTieApi::requireFeature('payments');$user=UthengaTieApi::requireAuthenticatedUser();
    requireAdminPermission('finance.manage');requireRecentAdminReauthentication('finance');
    UthengaTieApi::requireCsrf();UthengaTieApi::requireRateLimit('admin_payment_reconciliation',5,60,$requestId);
    if (!uthenga_financial_callback_commit_allowed()) { uthenga_financial_callback_block('admin_payment_reconciliation'); throw UthengaTieErrors::providerUnavailable('financial_callback_controls'); }
    $input=UthengaTieApi::input();$limit=filter_var($input['limit']??25,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100]]);if($limit===false)throw UthengaTieErrors::validation(['limit'=>'Use a limit from 1 to 100.']);
    $result=(new UthengaTieKernel())->payments->reconcilePending((int)$limit);
    UthengaTieObservability::log('payment.reconciliation_completed',$requestId,['module'=>'payments','status'=>($result['errors']??0)>0?'partial':'ok','candidate_count'=>$result['checked']??0]);
    UthengaTieApi::respond(['success'=>true,'request_id'=>$requestId,'reconciliation'=>$result]);
}catch(Throwable $error){UthengaTieApi::handleError($error,$requestId);}
