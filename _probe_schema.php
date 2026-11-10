<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (['login_failures','ip_whitelists','sensitive_access_logs','rnd_extension_applications','ai_providers','approval_flows','rnd_task_types'] as $t) {
    echo "$t: " . (\Schema::hasTable($t) ? implode(', ', \Schema::getColumnListing($t)) : 'TABLE MISSING') . "\n\n";
}
