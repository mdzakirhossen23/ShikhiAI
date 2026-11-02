<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Shared, locked counters outside the public web root. Never trust proxy IP headers.
function enforceRequestBudget(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (($origin !== '' && $origin !== 'https://shikhiai.baharsoftbd.com') || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
        http_response_code(403); echo json_encode(['error'=>'origin_not_allowed']); exit;
    }
    $path = '/home/baharsof/shikhi-ai-request-budget.json';
    $handle = @fopen($path, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if ($handle) fclose($handle);
        http_response_code(503); echo json_encode(['error'=>'temporarily_unavailable']); exit;
    }
    @chmod($path, 0600);
    $now = time();
    $stored = json_decode(stream_get_contents($handle), true);
    $buckets = is_array($stored) ? $stored : [];
    foreach ($buckets as $key => $bucket) {
        if (!is_array($bucket) || ($bucket['until'] ?? 0) <= $now) unset($buckets[$key]);
    }
    $ip = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $limits = ['minute-'.$ip=>[60,6], 'hour-'.$ip=>[3600,40], 'global'=>[3600,200]];
    $retry = 0;
    foreach ($limits as $key => [$window,$limit]) {
        if (!isset($buckets[$key])) $buckets[$key] = ['until'=>$now+$window,'count'=>0];
        if ($buckets[$key]['count'] >= $limit) $retry = max($retry, $buckets[$key]['until']-$now);
    }
    if (!$retry) foreach ($limits as $key => $unused) $buckets[$key]['count']++;
    rewind($handle); ftruncate($handle,0);
    $written = fwrite($handle,json_encode($buckets)); fflush($handle); flock($handle,LOCK_UN); fclose($handle);
    if ($written === false) { http_response_code(503); echo json_encode(['error'=>'temporarily_unavailable']); exit; }
    if ($retry) { http_response_code(429); header('Retry-After: '.max(1,$retry)); echo json_encode(['error'=>'rate_limited','retry_after'=>max(1,$retry)]); exit; }
}

$groqKey = trim((string)(getenv('GROQ_API_KEY') ?: ''));
$configPath = '/home/baharsof/yunet-ai-config.php';
if ($groqKey === '' && is_readable($configPath)) {
    $privateConfig = require $configPath;
    if (is_array($privateConfig) && is_string($privateConfig['GROQ_API_KEY'] ?? null)) {
        $groqKey = trim($privateConfig['GROQ_API_KEY']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') { echo json_encode(['service'=>'yunet-aignite26','configured'=>(bool)$groqKey,'provider'=>'Groq','storage'=>'none']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {http_response_code(405);echo json_encode(['error'=>'method_not_allowed']);exit;}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>5000) {http_response_code(413);echo json_encode(['error'=>'too_large']);exit;}
enforceRequestBudget();
$data=json_decode(file_get_contents('php://input'),true);
if (!is_array($data)) {http_response_code(400);echo json_encode(['error'=>'invalid_json']);exit;}
if (($data['action']??'')==='translate_tasks') {
 $tasks=$data['tasks']??null;$lang=$data['language']??'en';
 if(!is_array($tasks)||count($tasks)!==4||!in_array($lang,['en','bn','ar','nl','de'],true)){http_response_code(400);echo json_encode(['error'=>'invalid_fields']);exit;}
 foreach($tasks as $task){if(!is_string($task)||mb_strlen($task)>240){http_response_code(400);echo json_encode(['error'=>'invalid_fields']);exit;}}
 $key=$groqKey;if(!$key){http_response_code(503);echo json_encode(['error'=>'unconfigured']);exit;}
 $languages=['en'=>'English','bn'=>'Bengali (Bangla)','ar'=>'Modern Standard Arabic','nl'=>'Dutch (Nederlands)','de'=>'German (Deutsch)'];
 $payload=['model'=>'openai/gpt-oss-120b','max_completion_tokens'=>2048,'reasoning_effort'=>'medium','temperature'=>0.1,'response_format'=>['type'=>'json_object'],'messages'=>[['role'=>'system','content'=>'Translate exactly four educational learning milestone strings into '.$languages[$lang].'. Keep technical terms when necessary; preserve meaning and order. Return only JSON with key "tasks", an array of exactly four translated strings.'],['role'=>'user','content'=>json_encode($tasks,JSON_UNESCAPED_UNICODE)]]];
 $ch=curl_init('https://api.groq.com/openai/v1/chat/completions');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_TIMEOUT=>22]);$raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$transportError=curl_errno($ch);curl_close($ch);
 if($raw===false||$code!==200){http_response_code(502);echo json_encode(['error'=>'ai_unavailable','upstream_status'=>$code,'transport_error'=>$transportError]);exit;}
 $result=json_decode($raw,true);$translated=json_decode($result['choices'][0]['message']['content']??'',true)['tasks']??null;
 if(!is_array($translated)||count($translated)!==4){http_response_code(502);echo json_encode(['error'=>'invalid_ai_response']);exit;}
 foreach($translated as $task){if(!is_string($task)||mb_strlen($task)>240){http_response_code(502);echo json_encode(['error'=>'invalid_ai_response']);exit;}}
 echo json_encode(['ok'=>true,'tasks'=>array_values($translated)],JSON_UNESCAPED_UNICODE);exit;
}
$allowed=['Web development','Data analysis','English communication','UI/UX design','AI & prompt engineering'];
$goal=$data['goal']??'';$level=$data['level']??'';$hours=$data['hours']??0;$challenge=$data['challenge']??'';$lang=$data['language']??'en'; if(!in_array($lang,['en','bn','ar','nl','de'],true))$lang='en';
if(!in_array($goal,$allowed,true)||!in_array($level,['beginner','intermediate'],true)||!is_numeric($hours)||$hours<2||$hours>40||!is_string($challenge)||mb_strlen($challenge)>400){http_response_code(400);echo json_encode(['error'=>'invalid_fields']);exit;}
// Store this secret in a server environment variable (never in JS, HTML, or public_html).
$key=$groqKey;if(!$key){http_response_code(503);echo json_encode(['error'=>'unconfigured']);exit;}
$languages=['en'=>'English','bn'=>'Bengali (Bangla)','ar'=>'Modern Standard Arabic','nl'=>'Dutch (Nederlands)','de'=>'German (Deutsch)'];
$system='You are the yunet-aignite26 AI assistant, an educational planning assistant for Bangladeshi students. Return ONLY a JSON object with one key "tasks" containing exactly four concise strings, each a practical weekly milestone with a specific deliverable. Do not prefix strings with week numbers or labels. Use a concrete deliverable for each milestone. Do not include links or unsafe content. Avoid promising employment. Respond in the requested language for all four milestone strings. Do not mix other languages unless it is a necessary technical term.';
$prompt="Write all four task strings in {$languages[$lang]}. Build a four-week student learning roadmap. Skill: $goal. Level: $level. Hours available per week: $hours. Personal constraints: ".mb_substr($challenge,0,400);
$payload=['model'=>'openai/gpt-oss-120b','max_completion_tokens'=>2048,'reasoning_effort'=>'medium','temperature'=>0.4,'response_format'=>['type'=>'json_object'],'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$prompt]]];
$ch=curl_init('https://api.groq.com/openai/v1/chat/completions');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_TIMEOUT=>22]);$raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$transportError=curl_errno($ch);curl_close($ch);
if($raw===false||$code!==200){http_response_code(502);echo json_encode(['error'=>'ai_unavailable','upstream_status'=>$code,'transport_error'=>$transportError]);exit;}
$result=json_decode($raw,true);$message=$result['choices'][0]['message']['content']??'';$output=json_decode($message,true);$tasks=$output['tasks']??null;
if(!is_array($tasks)||count($tasks)!==4){http_response_code(502);echo json_encode(['error'=>'invalid_ai_response']);exit;}
foreach($tasks as $task){if(!is_string($task)||mb_strlen($task)>240){http_response_code(502);echo json_encode(['error'=>'invalid_ai_response']);exit;}}
echo json_encode(['ok'=>true,'tasks'=>array_values($tasks)],JSON_UNESCAPED_UNICODE);
