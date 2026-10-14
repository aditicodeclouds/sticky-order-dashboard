<?php
function decrypt_secret(string $value): string { if($value==='')return ''; $key=base64_decode($GLOBALS['config']['encryption_key']??'',true); if(!$key || strlen($key)!==32) throw new RuntimeException('Configure a valid 32-byte base64 encryption key.'); $raw=base64_decode($value,true); if(!$raw || strlen($raw)<29) return ''; $iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);$plain=openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);return $plain===false?'':$plain; }
function encrypt_secret(string $value): string { $key=base64_decode($GLOBALS['config']['encryption_key']??'',true); if(!$key || strlen($key)!==32) throw new RuntimeException('Configure a valid 32-byte base64 encryption key.');$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);return base64_encode($iv.$tag.$cipher); }
function sticky_config(): array { return ['endpoint'=>setting('sticky_endpoint',''),'username'=>setting('sticky_username',''),'password'=>decrypt_secret(setting('sticky_password_enc',''))]; }
function sticky_request(array $body): array {
 $c=sticky_config(); if(!$c['endpoint']||!filter_var($c['endpoint'],FILTER_VALIDATE_URL)||!in_array(parse_url($c['endpoint'],PHP_URL_SCHEME),['https','http'],true)) throw new RuntimeException('Configure a valid Sticky.io endpoint first.'); if(!$c['username']||!$c['password'])throw new RuntimeException('Sticky.io API credentials are not configured.');
 $opts=$GLOBALS['config']['sticky']??[]; $ch=curl_init($c['endpoint']); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$c['username'].':'.$c['password'],CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR),CURLOPT_TIMEOUT=>max(120,(int)($opts['timeout_seconds']??120)),CURLOPT_CONNECTTIMEOUT=>max(15,(int)($opts['connect_timeout_seconds']??15)),CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
 $raw=curl_exec($ch);$err=curl_error($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false)throw new RuntimeException('Sticky.io request failed (cURL '.$errno.'): '.($err?:'network or timeout error').'. Check endpoint/network settings and timeout.');if($status===429)throw new RuntimeException('Sticky.io rate limit reached. Please retry later.');if($status<200||$status>=300)throw new RuntimeException('Sticky.io returned HTTP '.$status.'. Check API configuration and permissions.');$json=json_decode($raw,true);if(!is_array($json)||json_last_error()!==JSON_ERROR_NONE)throw new RuntimeException('Sticky.io returned an invalid JSON response.');if(isset($json['response_code'])&&(string)$json['response_code']!=='100')throw new RuntimeException('Sticky.io API reported response code '.(string)$json['response_code'].'.');return $json;
}
/** Normalize order IDs returned either as an array or comma-separated string. */
function sticky_id_list(mixed $value): array {
 if(is_string($value)) $value=explode(',', $value);
 if(!is_array($value)) return [];
 $out=[]; foreach($value as $id){ if(is_scalar($id)){ $id=trim((string)$id); if($id!=='' && ctype_digit($id)) $out[]=$id; } }
 return array_values(array_unique($out));
}
function sticky_data_orders(array $response): array {
 $data=$response['data']??[]; if(is_string($data)){ $decoded=json_decode($data,true); if(is_array($decoded))$data=$decoded; }
 if(!is_array($data)) throw new RuntimeException('Sticky.io response has no valid order_view data object.');
 $orders=[]; foreach($data as $id=>$order){ if(is_array($order)){ if(!isset($order['order_id']) && is_scalar($id))$order['order_id']=(string)$id; $key=(string)($order['order_id']??$id); if($key!=='')$orders[$key]=$order; } }
 return $orders;
}
function sticky_orders(array $brand,string $start,string $end): array {
 $base=['campaign_id'=>normalize_ids((string)$brand['campaign_ids']),'start_date'=>date('m/d/Y',strtotime($start)),'end_date'=>date('m/d/Y',strtotime($end)),'criteria'=>'all','search_type'=>'all','return_type'=>'order_view']; if(!$base['campaign_id'])return [];
 $first=sticky_request($base); $orders=sticky_data_orders($first); $known=array_fill_keys(array_keys($orders),true);
 // Sticky order_view responses can cap detailed records at max_allowed_order_ids (commonly 500).
 // Retrieve every remaining ID in smaller order_find batches instead of silently dropping records.
 $pending=sticky_id_list($first['remaining_order_ids']??[]);
 // Some versions return the full matching ID list in order_ids, so use it to detect omitted details too.
 foreach(sticky_id_list($first['order_ids']??[]) as $id) if(!isset($known[$id])) $pending[]=$id;
 $pending=array_values(array_unique(array_filter($pending,fn($id)=>!isset($known[$id]))));
 $opts=$GLOBALS['config']['sticky']??[]; $batchSize=max(1,min(400,(int)($opts['order_ids_batch_size']??400))); $maxBatches=max(1,(int)($opts['max_pages']??100)); $batches=0;
 while($pending){
  if($batches++ >= $maxBatches) throw new RuntimeException('Sticky.io returned more remaining orders than configured batch limit. Increase sticky.max_pages in config/config.php; report was not marked complete.');
  $batch=array_splice($pending,0,$batchSize); $request=$base; $request['order_ids']=implode(',',$batch);
  $part=sticky_request($request); $partOrders=sticky_data_orders($part);
  foreach($partOrders as $id=>$order){ if(!isset($known[$id])){$orders[$id]=$order;$known[$id]=true;} }
  $more=sticky_id_list($part['remaining_order_ids']??[]);
  foreach(sticky_id_list($part['order_ids']??[]) as $id) if(!isset($known[$id]) && !in_array($id,$batch,true)) $more[]=$id;
  foreach($more as $id) if(!isset($known[$id]) && !in_array($id,$pending,true)) $pending[]=$id;
  // Do not endlessly retry an ID Sticky.io did not return in this batch.
  $returned=array_fill_keys(array_keys($partOrders),true);
  foreach($batch as $id) if(!isset($known[$id]) && !isset($returned[$id])) throw new RuntimeException('Sticky.io did not return order details for order ID '.$id.'. Check whether order_ids is supported by this endpoint or contact Sticky.io support.');
 }
 return array_values($orders);
}
function classify_order(array $order): string {
 $oid=trim((string)($order['order_id']??''));$pid=trim((string)($order['parent_id']??'')); if($oid===''||$pid==='')return 'unclassified';
 $productIds=[];foreach(($order['products']??[]) as $p){if(isset($p['product_id'])&&ctype_digit((string)$p['product_id']))$productIds[]=(int)$p['product_id'];}
 if(!$productIds)return 'unclassified';$main=parse_ids(setting('main_product_ids',''));$upsell=parse_ids(setting('upsell_product_ids',''));$subs=parse_ids(setting('subscription_product_ids',''));
 // Rebills are identified by distinct order and parent IDs. Main/upsell matches cannot override this transaction relationship.
 if($oid!==$pid){ if(array_intersect($productIds,$subs))return 'subscription'; if(array_intersect($productIds,$main))return 'unclassified'; if(array_intersect($productIds,$upsell))return 'upsell'; return 'unclassified'; }
 // Original/root transaction: count only configured main product, never upsell-only or subscription-only signups.
 if(array_intersect($productIds,$main))return 'initial'; if(array_intersect($productIds,$upsell))return 'upsell'; if(array_intersect($productIds,$subs))return 'signup'; return 'unclassified';
}
function order_report_date(array $order): string { $v=trim((string)($order['time_stamp']??'')); if($v==='')return ''; $v=urldecode($v); $ts=strtotime($v); return $ts===false?'':date('Y-m-d',$ts); }
