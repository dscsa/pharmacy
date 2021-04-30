<?php

require 'parse_sigs.php';

$parser = new SigParser();

$correct_pairs = [

    // TODO: Check for repeated wording in "multiple sigs"
    // "1 tablet by mouth daily; TAKE ONE TABLET BY MOUTH ONCE DAILY" => 1,

    "Take 7.5mg three days per week (M,W,F) and 5mg four days per week OR as directed per Coumadin Clinic." => [
        "drug_name" => "WARFARIN SODIUM 5MG TAB",
        "expected" => [
            "sig_qty" => (4.5 + 4) * DAYS_STD / 7,
            "sig_days" => DAYS_STD,
            "sig_unit" => "TAB"
        ]
    ],
    "1 tablet by mouth every 8 hours as needed for Blood Pressure greater than 140/90" => [
        "drug_name" => "CLONIDINE 0.1MG TAB",
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 4 tablets by mouth 3 times a day with meal and 2 tablets twice a day with snacks" => [
        "expected" => [
            "sig_qty" => 16 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablets"
        ]
    ],
    "1 capsule by mouth at bedtime for 1 week then 2 capsules at bedtime" => [
        "expected" => [
            "sig_qty" => 7 + 2 * (DAYS_STD - 7),
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule"
        ]
    ],
    "Take 1 tablet (10 mg) by mouth 2 times per day for 21 days with food, then increase to 2 tablets (20 mg) BID" => [
        "expected" => [
            "sig_qty" => 2 * 21 + 4 * (DAYS_STD - 21),
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    // Outlier
    // "Take 2 tablets in the morning and 1 at noon and 1 at supper" => [
    //     "sig_qty" => 4 * DAYS_STD,
    //     "sig_days" => DAYS_STD,
    //     "sig_unit" => "tablets"
    // ],
    "Take 1 capsule(s) 3 times a day by oral route with meals for 7 days." => [
        "expected" => [
            "sig_qty" => 21,
            "sig_days" => 7,
            "sig_unit" => "capsule"
        ]
    ],
    // Outlier
    "1 capsule by mouth every day for 7 days then continue on with 60mg capsuled" => [
        "drug_name" => "DULOXETINE DR 30MG CAP",
        "expected" => [
            "sig_qty" => 7 + 2 * (DAYS_STD - 7),
            "sig_days" => DAYS_STD,
            "sig_unit" => "CAP"
        ]
    ],
    "Take 1 tablet by mouth 1 time daily then, if no side effects, increase to 1 tablet 2 times daily with meals" => [
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,    // Uncertain
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "take 10 tablets (40MG total)  by ORAL route   every day for  4 days only" => [
        "expected" => [
            "sig_qty" => 40,
            "sig_days" => 4,
            "sig_unit" => "tablets"
        ]
    ],
    "Take 1 tablet (12.5 mg) by mouth per day in the morning" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 capsule by mouth 30 minutes after the same meal each day" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule",
        ]
    ],
    "1 capsule by mouth every day (Start after finishing 30mg capsules first)" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule"
        ]
    ],
    "1 capsule by mouth twice a day" => [
        "expected" => [
            "sig_qty" => 2 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule"
        ]
    ],
    "1 capsule once daily 30 minutes after the same meal each day" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule"
        ]
    ],
    "1 tablet (5 mg total) by PEG Tube route 2 (two) times a day" => [
        "expected" => [
            "sig_qty" => 2 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth  every morning" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth at bedtime" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth at bedtime as directed" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth at bedtime as needed" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth at bedtime mood" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth daily" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth day" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth every 8 hours" => [
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "Take 5 tablets by mouth once  at bedtime" => [
        "expected" => [
            "sig_qty" => 5 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablets"
        ]
    ],
    "Take  1 TO 2 capsules by mouth 3 times a day as needed FOR NERVE PAINS" => [
        "expected" => [
            "sig_qty" => 6 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsules"
        ]
    ],
    // Outlier?
    "Take 1 capsule once a day take along with 40mg for total of 60mg" => [
        "drug_name" => "PROZAC 20MG PULVULE",
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule"
        ]
    ],
    "1 capsule as needed every 6 hrs Orally 30 day(s)" => [
        "expected" => [
            "sig_qty" => 120,
            "sig_days" => 30,
            "sig_unit" => "capsule"
        ]
    ],
    "1 tablet every 6 to 8 hours as needed Orally 30 day(s)" => [
        "expected" => [
            "sig_qty" => 90,     // or 120?
            "sig_days" => 30,
            "sig_unit" => "tablet"
        ]
    ],
    "Take 1 tablet by mouth every night as needed sleep" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "Take 1 tablet (800 mg) by oral route 3 times per day with food as needed for pain" => [
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "Take 1 tablet by mouth 3 times a day as needed" => [
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],

    // TODO: For now taking the unit which is in the sig itself
    // Unless it's vials, in which case parse it to ml.
    "Use 1 vial via nebulizer every 6 hours" => [
        "drug_name" => "ALBUTEROL SUL 2.5MG/3ML SOLN",
        "expected" => [
            "sig_qty" => 12 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "ml"
        ]
    ],
    "Take 20 mEq by mouth 2 (two) times a day." => [
        "expected" => [
            "sig_qty" => 40 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "mEq"
        ]
    ],
    "Inhale 2 puff(s) every 4 hours by inhalation route." => [
        "expected" => [
            "sig_qty" => 12 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "puff"
        ]
    ]

];

foreach($correct_pairs as $text => $props) {
    $result = $parser->parse($text, $props['drug_name']);

    $expected = $props['expected'];
    foreach ($expected as $key => $val) {
        $msg = "For $key expected ".$expected[$key].", got ".$result[$key].". \n\tSig: $text\n";
        if (is_float($expected[$key])) {
            assert(abs($expected[$key] - $result[$key]) < 0.001, $msg);
        } else {
            assert($expected[$key] == $result[$key], $msg);
        }
    }
}

?>