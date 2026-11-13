<?php

return [
    // 回收站保留天数。到期后由 trash:purge-expired 每天自动彻底删除。
    'retention_days' => (int) env('TRASH_RETENTION_DAYS', 15),

    // 孤儿文件巡检宽限期（分钟），避免删除刚上传但尚未落库的文件。
    'gc_grace_minutes' => (int) env('TRASH_GC_GRACE_MINUTES', 60),

    // 协同文档历史版本保留数量，超出后由 files:gc --fix 清理。
    'file_version_keep' => (int) env('TRASH_FILE_VERSION_KEEP', 3),
];
