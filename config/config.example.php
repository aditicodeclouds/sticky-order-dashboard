<?php
// Copy to config.php. Keep config.php out of version control and outside public web root where possible.
return [
  'db' => ['host'=>'127.0.0.1','name'=>'sticky_order_dashboard','user'=>'sticky_app','pass'=>'CHANGE_ME','charset'=>'utf8mb4'],
  'app' => ['name'=>'Sticky Order Dashboard','timezone'=>'Asia/Kolkata','base_url'=>'http://localhost/sticky-order-dashboard','session_name'=>'sticky_dashboard_session'],
  // Generate with: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
  'encryption_key' => 'REPLACE_WITH_BASE64_32_BYTE_KEY',
<<<<<<< HEAD
  'sticky' => ['timeout_seconds'=>120,'connect_timeout_seconds'=>15,'max_pages'=>100,'order_ids_batch_size'=>400],
=======
  'sticky' => ['timeout_seconds'=>120,'connect_timeout_seconds'=>15,'max_pages'=>100,'order_ids_batch_size'=>100],
>>>>>>> f6d30d4 (gg)
  'mail' => ['from'=>'noreply@example.com','reset_base_url'=>'http://localhost/sticky-order-dashboard/reset_password.php'],
];
