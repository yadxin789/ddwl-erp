<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OnlyOffice DocumentServer 配置
    |--------------------------------------------------------------------------
    |
    | server_url      : 浏览器访问 OnlyOffice 的地址（本机默认 8080）
    | jwt_secret      : 与 DocumentServer 的 JWT_SECRET 保持一致；留空表示服务端未启用 JWT
    | pm_internal_url : DocumentServer 访问本系统的地址。同机原生安装用 127.0.0.1；
    |                   Docker Desktop 用 host.docker.internal；云主机用公网地址。
    | template_dir    : 内置模板目录（相对 storage/app）
    |
    */

    'server_url' => env('ONLYOFFICE_SERVER_URL', 'http://127.0.0.1:8080'),

    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET', ''),

    'pm_internal_url' => env('ONLYOFFICE_PM_INTERNAL_URL', 'http://127.0.0.1:8000'),

    'template_dir' => 'templates',

    // OnlyOffice 是否启用（关闭时协同办公回退到纯文本编辑）
    'enabled' => env('ONLYOFFICE_ENABLED', true),

];
