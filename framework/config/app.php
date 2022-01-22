<?php
return [
    'debug' => 1,
    'root' => ROOT,
    'db_driver' => '\\Medoo\\Medoo',
    'db_connection' => "dev",
    'global_vars' => [
        'template_vars' => [
            'layuicss' => '',
            'layuijs' => '<script src="https://www.layuicdn.com/auto/layui.js" v="2.5.6" e="layui" charset="utf-8"></script>',
            'root' => ROOT,
        ]
    ]
];