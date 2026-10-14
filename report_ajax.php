<?php
declare(strict_types=1);
require __DIR__.'/includes/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
function report_json(array $data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') report_json(['ok'=>false,'message'=>'POST required.'],405);
if (!hash_equals((string)($_SESSION['_csrf']??''),(string)($_POST['_csrf']??''))) report_json(['ok'=>false,'message'=>'Your session expired. Refresh the page and try again.'],419);
$columns=['order_id'=>'Order ID','parent_id'=>'Parent ID','time_stamp'=>'Report Date (time_stamp)','campaign_id'=>'Campaign ID','first_name'=>'First Name','last_name'=>'Last Name','email_address'=>'Email','order_total'=>'Order Total','order_status'=>'Status','main_product_id'=>'Main Product ID','billing_cycle'=>'Billing Cycle','is_recurring'=>'Recurring','customer_id'=>'Customer ID'];
$kind=(string)($_POST['kind']??'');
if ($kind==='save_columns') {
  $report=($_POST['report_kind']??'initial')==='sub'?'sub':'initial';
  $selected=array_values(array_intersect((array)($_POST['columns']??[]),array_keys($columns)));
  if (!$selected) report_json(['ok'=>false,'message'=>'Choose at least one field.'],422);
  $_SESSION[$report.'_columns']=$selected;
  session_write_close();
  report_json(['ok'=>true,'message'=>'Fields saved.','columns'=>$selected]);
}
$brand=current_brand();
if(!$brand) report_json(['ok'=>false,'message'=>'No active brand is assigned to your account.'],403);
$today=date('Y-m-d');
$validDate=static fn($d)=>is_string($d)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)&&strtotime($d)!==false;
$readRange=static function(string $prefix) use($today,$validDate): array { $start=$_POST[$prefix.'_start']??$today; $end=$_POST[$prefix.'_end']??$today; if(!$validDate($start)||!$validDate($end)||$start>$end) throw new InvalidArgumentException('Please select a valid date range.'); return [$start,$end]; };
function report_value(array $order,string $key): string { $value=$order[$key]??''; if(is_array($value)||is_object($value))return ''; $value=(string)$value; if(in_array($key,['time_stamp','acquisition_date'],true))$value=urldecode($value); return $value; }
function report_detail_link(array $order,string $start,string $end): string { $id=(string)($order['order_id']??''); return 'order.php?id='.rawurlencode($id).'&start='.rawurlencode($start).'&end='.rawurlencode($end); }
try {
 if($kind==='summary') {
  session_write_close();
  $orders=sticky_orders($brand,$today,$today); $initial=0;$subs=0;$other=0;
  foreach($orders as $o){if(order_report_date($o)!==$today)continue;$type=classify_order($o);if($type==='initial')$initial++;elseif($type==='subscription')$subs++;elseif(in_array($type,['unclassified','signup'],true))$other++;}
  report_json(['ok'=>true,'initial'=>$initial,'subscription'=>$subs,'other'=>$other]);
 }
 if(!in_array($kind,['initial','sub'],true)) report_json(['ok'=>false,'message'=>'Unknown report type.'],422);
 $prefix=$kind==='sub'?'sub':'initial'; [$start,$end]=$readRange($prefix);
 $chosen=$_SESSION[$prefix.'_columns']??array_keys($columns); $chosen=array_values(array_intersect((array)$chosen,array_keys($columns))); if(!$chosen)$chosen=array_keys($columns);
 session_write_close();
 $orders=sticky_orders($brand,$start,$end); $filtered=[];
 foreach($orders as $order){$date=order_report_date($order);if($date<$start||$date>$end)continue;$type=classify_order($order);if(($kind==='initial'&&$type==='initial')||($kind==='sub'&&$type==='subscription'))$filtered[]=['order'=>$order,'date'=>$date];}
 usort($filtered,static fn($a,$b)=>strcmp($b['date'],$a['date']) ?: strcmp((string)($b['order']['order_id']??''),(string)($a['order']['order_id']??'')));
 ob_start();
 if($kind==='initial') {
  if(!$filtered) echo '<tr><td colspan="'.(count($chosen)+1).'">No qualifying initial orders in this date range.</td></tr>';
  foreach($filtered as $item){$o=$item['order'];echo '<tr>';foreach($chosen as $key){echo '<td>'.e(report_value($o,$key)).'</td>'; }echo '<td><a href="'.e(report_detail_link($o,$start,$end)).'">View order</a></td></tr>';}
 } else {
  $groups=[];foreach($filtered as $item)$groups[$item['date']][]=$item;
  if(!$groups) echo '<tr><td colspan="3">No qualifying subscription rebills in this date range.</td></tr>';
  foreach($groups as $date=>$items){$target='d'.substr(hash('sha256',$date),0,10);echo '<tr><td>'.e($date).'</td><td>'.count($items).'</td><td><button type="button" class="toggle-detail" data-target="#'.e($target).'">View details</button></td></tr>';
   echo '<tr id="'.e($target).'" class="detail-row" style="display:none"><td colspan="3"><div class="table-wrap"><table><thead><tr>';foreach($chosen as $key)echo '<th>'.e($columns[$key]).'</th>';echo '<th>Details</th></tr></thead><tbody>';
   foreach($items as $item){$o=$item['order'];echo '<tr>';foreach($chosen as $key)echo '<td>'.e(report_value($o,$key)).'</td>';echo '<td><a href="'.e(report_detail_link($o,$start,$end)).'">View order</a></td></tr>';}
   echo '</tbody></table></div></td></tr>';
  }
 }
 $html=(string)ob_get_clean();
 report_json(['ok'=>true,'html'=>$html,'count'=>count($filtered),'columns'=>$chosen,'message'=>'Loaded '.count($filtered).' matching orders.']);
} catch (InvalidArgumentException $e) { report_json(['ok'=>false,'message'=>$e->getMessage()],422); }
catch (Throwable $e) { report_json(['ok'=>false,'message'=>$e->getMessage()],502); }
