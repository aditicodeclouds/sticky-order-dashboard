<?php
// Run: php tests/classifier_test.php
// This is an isolated logic test; does not call Sticky.io or require a database.
function parse_ids(?string $raw): array { if(!$raw)return []; $ids=array_values(array_unique(array_filter(array_map('trim',explode(',',$raw)),fn($x)=>$x!==''&&ctype_digit($x))));usort($ids,fn($a,$b)=>(int)$b<=>(int)$a);return array_map('intval',$ids); }
function setting(string $key,?string $default=null):?string{return ['main_product_ids'=>'382,386','upsell_product_ids'=>'401,400','subscription_product_ids'=>'379,378'][$key]??$default;}
function classify_order(array $order): string {$oid=trim((string)($order['order_id']??''));$pid=trim((string)($order['parent_id']??''));if($oid===''||$pid==='')return 'unclassified';$productIds=[];foreach(($order['products']??[]) as $p)if(isset($p['product_id'])&&ctype_digit((string)$p['product_id']))$productIds[]=(int)$p['product_id'];if(!$productIds)return 'unclassified';$main=parse_ids(setting('main_product_ids',''));$upsell=parse_ids(setting('upsell_product_ids',''));$subs=parse_ids(setting('subscription_product_ids',''));if($oid!==$pid){if(array_intersect($productIds,$subs))return 'subscription';if(array_intersect($productIds,$main))return 'unclassified';if(array_intersect($productIds,$upsell))return 'upsell';return 'unclassified';}if(array_intersect($productIds,$main))return 'initial';if(array_intersect($productIds,$upsell))return 'upsell';if(array_intersect($productIds,$subs))return 'signup';return 'unclassified';}
$tests=[
 ['root main',['order_id'=>'100','parent_id'=>'100','products'=>[['product_id'=>'382']]],'initial'],
 ['separate upsell',['order_id'=>'101','parent_id'=>'100','products'=>[['product_id'=>'401']]],'upsell'],
 ['root subscription signup',['order_id'=>'102','parent_id'=>'102','products'=>[['product_id'=>'379']]],'signup'],
 ['subsequent rebill',['order_id'=>'103','parent_id'=>'100','products'=>[['product_id'=>'379']]],'subscription'],
 ['missing parent',['order_id'=>'104','products'=>[['product_id'=>'379']]],'unclassified'],
 ['multi product root main wins',['order_id'=>'105','parent_id'=>'105','products'=>[['product_id'=>'382'],['product_id'=>'401']]],'initial'],
 ['unexpected product',['order_id'=>'106','parent_id'=>'106','products'=>[['product_id'=>'999']]],'unclassified'],
 ['overlap main/sub rebill is ambiguous',['order_id'=>'107','parent_id'=>'100','products'=>[['product_id'=>'382'],['product_id'=>'379']]],'subscription'],
];$failed=0;foreach($tests as [$name,$o,$expected]){$actual=classify_order($o);$ok=$actual===$expected;echo ($ok?'PASS':'FAIL')." $name expected=$expected actual=$actual\n";if(!$ok)$failed++;}exit($failed?1:0);
