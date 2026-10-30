<?php

return [
    'required' => ':attribute দেওয়া আবশ্যক।',
    'email' => ':attribute একটি সঠিক ইমেইল ঠিকানা হতে হবে।',
    'string' => ':attribute লেখা আকারে দিতে হবে।',
    'integer' => ':attribute একটি সংখ্যা হতে হবে।',
    'max' => [
        'string' => ':attribute সর্বোচ্চ :max অক্ষরের হতে পারবে।',
    ],
    'between' => [
        'numeric' => ':attribute অবশ্যই :min থেকে :max এর মধ্যে হতে হবে।',
    ],
    'date_format' => ':attribute সঠিক ফরম্যাটে দিন।',
    'after_or_equal' => ':attribute অতীতের তারিখ হতে পারবে না।',
    'before_or_equal' => ':attribute অনেক দূরের তারিখ হতে পারবে না।',
    'regex' => ':attribute সঠিক ফরম্যাটে দিন।',

    'attributes' => [
        'name' => 'নাম',
        'email' => 'ইমেইল',
        'phone' => 'ফোন নম্বর',
        'date' => 'তারিখ',
        'time' => 'সময়',
        'party_size' => 'অতিথির সংখ্যা',
        'message' => 'বার্তা',
        'subject' => 'বিষয়',
        'rating' => 'রেটিং',
        'comment' => 'মতামত',
        'body' => 'উত্তর',
    ],
];
