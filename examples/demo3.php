<?php

    use Coco\validate\Validate;

    require './../vendor/autoload.php';

    $rule = [
        Validate::SCENE_ADD => [

            "name"  => [
                "require"        => "姓名必须填写",
                //                    "lengthRange:7"   => "姓名至少7位",
                //                    "lengthRange:7,9" => "姓名7-9位",
                "lengthRange:,9" => "姓名最多9位",
            ],
            "age"   => [
                "number"      => "必须是数字",
                ">:15"        => "必须大于15",
                "notIn:2,3,4" => "不能是2,3,4",
                //                    "between:1,5" => "年龄必须在1-5之间",
            ],
            "times" => [
                "require"       => "次数必须填写",
                'regex:#^\d+$#' => '必须全是数字',
            ],

            "pwd"         => [
                "require" => "pwd必须填写",
            ],
            "pwd_confirm" => [
                "require"       => "pwd_confirm必须填写",
                'sameField:pwd' => '必须和pwd一样',
            ],

            "date" => [
                "afterDate:2012-9-16 14:25:55" => "时间太早",
            ],

            "isArray" => [
                "array" => "必须是array",
            ],

            "phone" => [
                "require" => "phone必须填写",
                "mobile"  => "必须是手机号码",
            ],
        ],
    ];

    var_dump(Validate::verify('2012-9-16 14:25:59', 'afterDate:2012-9-16 14:25:56'));

    var_dump(Validate::verifyBatch('4', [
        ">:2",
        "notIn:2,3,4",
    ]));
