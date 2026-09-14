<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireLogin();
$operator=Auth::user();
if(!$operator || (int)$operator['is_superadmin']!==1){http_response_code(403);exit('Nur der Plattform-Superadministrator darf den Supportmodus verwenden.');}

if(($_GET['action']??'')==='stop'){
    $sessionId=(int)($_SESSION['support_session_id']??0);
    if($sessionId && TenantAccess::tableExists('tenant_support_sessions'))db()->prepare('UPDATE tenant_support_sessions SET ended_at=NOW() WHERE id=? AND operator_user_id=?')->execute([$sessionId,(int)$operator['id']]);
    unset($_SESSION['support_tenant_id'],$_SESSION['support_session_id']);
    flash('success','Supportmodus wurde beendet.');redirect('../?page=dashboard');
}

if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Methode nicht erlaubt.');}
require_csrf();
if(!TenantAccess::tableExists('tenant_support_sessions'))redirect('../update/');
$tenantId=(int)($_POST['tenant_id']??0);$reason=trim((string)($_POST['reason_text']??''));
if($reason==='' || $tenantId<1)throw new RuntimeException('Mandant und Supportgrund sind erforderlich.');
$check=db()->prepare('SELECT COUNT(*) FROM organizations WHERE id=?');$check->execute([$tenantId]);if(!(int)$check->fetchColumn())throw new RuntimeException('Mandant nicht gefunden.');
$stmt=db()->prepare('INSERT INTO tenant_support_sessions (operator_user_id,tenant_id,reason_text,ip_address) VALUES (?,?,?,?)');$stmt->execute([(int)$operator['id'],$tenantId,$reason,substr((string)($_SERVER['REMOTE_ADDR']??'unknown'),0,45)]);
$_SESSION['support_tenant_id']=$tenantId;$_SESSION['support_session_id']=(int)db()->lastInsertId();
audit('support_mode_start','organizations',$tenantId,'Supportmodus gestartet: '.$reason);redirect('../?page=dashboard');
