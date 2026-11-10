<?php

/**
 * P1-07 电商平台预置清单（只做接入，不做交易闭环）
 * 每项：
 *  - name/region/region_label
 *  - required_credentials：key => 中文说明（向导按此生成输入项）
 *  - apply_url：去哪里申请 appkey / 授权
 *  - field_notes：接单需要对齐的字段与注意事项
 *  - capabilities：平台能力声明（P3-08 起 pullOrders/pushShipment 开启，ackOrder 仍待接入）
 */
return [
    'presets' => [
        /* ---------------- 国内 ---------------- */
        'taobao' => [
            'name' => '淘宝 / 天猫',
            'region' => 'domestic',
            'apply_url' => 'https://open.taobao.com/',
            'required_credentials' => [
                'app_key' => 'App Key（开放平台应用）',
                'app_secret' => 'App Secret',
                'session_key' => 'Session Key（店铺授权后获取）',
            ],
            'field_notes' => '订单号 tid、买家 nick、收货地址、支付金额、SKU 的 outer_id 必须与物料编码一致；回调需在开放平台配置消息服务。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'jd' => [
            'name' => '京东',
            'region' => 'domestic',
            'apply_url' => 'https://open.jd.com/',
            'required_credentials' => [
                'app_key' => 'App Key',
                'app_secret' => 'App Secret',
                'access_token' => 'Access Token（授权后获取）',
            ],
            'field_notes' => '订单号 orderId、商家 SKU、收货人脱敏字段；需申请"订单查询"与"发货回传"权限包。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'pdd' => [
            'name' => '拼多多',
            'region' => 'domestic',
            'apply_url' => 'https://open.pinduoduo.com/',
            'required_credentials' => [
                'client_id' => 'Client ID',
                'client_secret' => 'Client Secret',
                'access_token' => 'Access Token',
            ],
            'field_notes' => '订单号 order_sn、goods_id 与商家编码映射；发货需物流公司编码表对齐。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'douyin' => [
            'name' => '抖音电商',
            'region' => 'domestic',
            'apply_url' => 'https://op.jinritemai.com/',
            'required_credentials' => [
                'app_key' => 'App Key',
                'app_secret' => 'App Secret',
                'access_token' => 'Access Token',
            ],
            'field_notes' => '订单号 order_id、sku_order_id；发货回传需要物流公司编码；注意订单状态枚举与平台不一致。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'kuaishou' => [
            'name' => '快手电商',
            'region' => 'domestic',
            'apply_url' => 'https://open.kwaixiaodian.com/',
            'required_credentials' => [
                'app_key' => 'App Key',
                'app_secret' => 'App Secret',
                'access_token' => 'Access Token',
            ],
            'field_notes' => '订单号 oid、商品 ID 与 SKU 编码；分销订单需区分供货模式。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'xiaohongshu' => [
            'name' => '小红书',
            'region' => 'domestic',
            'apply_url' => 'https://open.xiaohongshu.com/',
            'required_credentials' => [
                'app_id' => 'App ID',
                'app_secret' => 'App Secret',
                'access_token' => 'Access Token',
            ],
            'field_notes' => '订单号 order_id、SKU 编码；内容种草转电商订单需注意来源标识。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'youzan' => [
            'name' => '有赞',
            'region' => 'domestic',
            'apply_url' => 'https://open.youzan.com/',
            'required_credentials' => [
                'client_id' => 'Client ID',
                'client_secret' => 'Client Secret',
                'access_token' => 'Access Token',
            ],
            'field_notes' => '订单号 tid、商品 item_id；有赞云网关需按店铺授权。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],

        /* ---------------- 国外 ---------------- */
        'amazon' => [
            'name' => 'Amazon',
            'region' => 'overseas',
            'apply_url' => 'https://sellercentral.amazon.com/',
            'required_credentials' => [
                'seller_id' => 'Seller ID',
                'marketplace_id' => 'Marketplace ID',
                'refresh_token' => 'LWA Refresh Token',
                'client_id' => 'LWA Client ID',
                'client_secret' => 'LWA Client Secret',
            ],
            'field_notes' => 'Amazon Order ID、ASIN/SKU、Marketplace 分开配置；SP-API 有区域端点（NA/EU/FE）。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'shopify' => [
            'name' => 'Shopify',
            'region' => 'overseas',
            'apply_url' => 'https://shopify.dev/docs/apps/auth',
            'required_credentials' => [
                'shop_domain' => '店铺域名（xxx.myshopify.com）',
                'access_token' => 'Admin API Access Token',
            ],
            'field_notes' => '订单号 order name/number、SKU、币种；API 版本需固定（如 2024-10）。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'ebay' => [
            'name' => 'eBay',
            'region' => 'overseas',
            'apply_url' => 'https://developer.ebay.com/',
            'required_credentials' => [
                'client_id' => 'Client ID (App ID)',
                'client_secret' => 'Client Secret (Cert ID)',
                'refresh_token' => 'OAuth Refresh Token',
            ],
            'field_notes' => 'Order ID、Item ID、SKU；站点（US/UK/DE）需分开配置。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'tiktok_shop' => [
            'name' => 'TikTok Shop',
            'region' => 'overseas',
            'apply_url' => 'https://partner.tiktokshop.com/',
            'required_credentials' => [
                'app_key' => 'App Key',
                'app_secret' => 'App Secret',
                'access_token' => 'Access Token',
                'shop_cipher' => 'Shop Cipher',
            ],
            'field_notes' => '订单号 order_id、SKU ID；站点区分跨境/本土，需 shop_cipher 指定店铺。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'shopee' => [
            'name' => 'Shopee',
            'region' => 'overseas',
            'apply_url' => 'https://open.shopee.com/',
            'required_credentials' => [
                'partner_id' => 'Partner ID',
                'partner_key' => 'Partner Key',
                'shop_id' => 'Shop ID',
                'access_token' => 'Access Token',
            ],
            'field_notes' => '订单号 order_sn、item_id、model SKU；各站点域名不同（sg/my/th 等）。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'lazada' => [
            'name' => 'Lazada',
            'region' => 'overseas',
            'apply_url' => 'https://open.lazada.com/',
            'required_credentials' => [
                'app_key' => 'App Key',
                'app_secret' => 'App Secret',
                'access_token' => 'Access Token',
            ],
            'field_notes' => '订单号 order_id、SellerSKU；各国家站点需分开配置。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'walmart' => [
            'name' => 'Walmart',
            'region' => 'overseas',
            'apply_url' => 'https://developer.walmart.com/',
            'required_credentials' => [
                'client_id' => 'Client ID',
                'client_secret' => 'Client Secret',
                'consumer_channel' => 'Consumer Channel',
            ],
            'field_notes' => 'Purchase Order 号、SKU、GTIN；发货需 Carrier 与 Tracking 对齐。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
        'temu' => [
            'name' => 'Temu',
            'region' => 'overseas',
            'apply_url' => 'https://partner.temu.com/',
            'required_credentials' => [
                'app_key' => 'App Key',
                'app_secret' => 'App Secret',
                'access_token' => 'Access Token',
            ],
            'field_notes' => '父/子订单号、SKU；全托管与半托管模式字段不同，需先定模式。',
            'capabilities' => ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
        ],
    ],

    /** 是否真实发起连通性探测（测试/离线部署可关，仅做凭证完整性校验） */
    'probe_enabled' => env('PLATFORM_PROBE_ENABLED', true),

    /** 自定义平台的鉴权方式枚举 */
    'auth_types' => [
        'apikey' => 'API Key（请求头携带）',
        'oauth2' => 'OAuth2（Bearer Token）',
        'signature' => '签名（AppSecret + HMAC）',
    ],

    /** 同步频率枚举（本期默认 manual，不做定时任务） */
    'sync_frequencies' => [
        'manual' => '手动',
        '15m' => '每 15 分钟',
        'hourly' => '每小时',
        'daily' => '每天',
    ],
];

