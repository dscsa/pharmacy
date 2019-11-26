<?php

const LIVE_MODE = true;

const ADDED_MANUALLY = [
  "MANUAL",
  "WEBFORM"
];

const PICK_LIST_FOLDER_NAME = 'OLD';
const INVOICE_FOLDER_NAME   = 'OLD';  //Published

const PAYMENT_TOTAL_NEW_PATIENT = 6;

const PAYMENT_METHOD = [
  'COUPON'       => 'COUPON',
  'MANUAL'       => 'MANUAL',
  'AUTOPAY'      => 'AUTOPAY',
  'CARD EXPIRED' => 'CARD EXPIRED'
];

const STOCK_LEVEL = [
  'HIGH SUPPLY'  => 'HIGH SUPPLY',
  'LOW SUPPLY'   => 'LOW SUPPLY',
  'ONE TIME'     => 'ONE TIME',
  'REFILL ONLY'  => 'REFILL ONLY',
  'OUT OF STOCK' => 'OUT OF STOCK',
  'NOT OFFERED'  => 'NOT OFFERED'
];

const RX_MESSAGE = [
  'NO ACTION STANDARD FILL' => [
    'EN' => '',
    'ES' => ''
  ],
  'NO ACTION PAST DUE AND SYNC TO ORDER' => [
    'EN' => 'is past due and was synced to this Order *',
    'ES' => ''
  ],
  'NO ACTION DUE SOON AND SYNC TO ORDER' => [
    'EN' => 'is due soon and was synced to this Order *',
    'ES' => ''
  ],
  'NO ACTION SYNC TO DATE' => [
    'EN' => 'is due soon and was synced to this Order *',
    'ES' => ''
  ],
  'NO ACTION RX OFF AUTOFILL' => [
    'EN' => 'has autorefill off but was requested to be filled',
    'ES' => ''
  ],
  'NO ACTION RECENT FILL' => [
    'EN' => 'was filled recently and not due again until $NextRefill',
    'ES' => ''
  ],
  'NO ACTION NOT DUE' => [
    'EN' => 'is due for a refill on $NextRefill',
    'ES' => ''
  ],
  'NO ACTION CHECK SIG' => [
    'EN' => 'was prescribed in an unusually high qty and needs to be reviewed by a pharmacist',
    'ES' => ''
  ],
  'NO ACTION MISSING GCN' => [
    'EN' => 'needs to be checked to see if it is available',
    'ES' => ''
  ],
  'NO ACTION LOW STOCK' => [
    'EN' => 'is short filled because this drug is low in stock',
    'ES' => ''
  ],
  'NO ACTION LOW REFILL' => [
    'EN' => 'is short filled because this Rx had limited refills',
    'ES' => ''
  ],
  'NO ACTION WILL TRANSFER CHECK BACK' => [
    'EN' => 'is not offered and will be transferred to your local pharmacy. Check back in 3 months',
    'ES' => ''
  ],
  'NO ACTION WILL TRANSFER' => [
    'EN' => 'is not offered and will be transferred to your local pharmacy',
    'ES' => ''
  ],
  'NO ACTION WAS TRANSFERRED' => [
    'EN' => 'was transferred out to your local pharmacy on $RxChanged',
    'ES' => ''
  ],
  'NO ACTION LIVE INVENTORY ERROR' => [
    'EN' => 'is awaiting manual inventory verification',
    'ES' => ''
  ],
  'NO ACTION SHOPPING ERROR' => [
    'EN' => 'is awaiting manual inventory retrieval',
    'ES' => ''
  ],'

  //ACTION BY USER REQUIRED BEFORE (RE)FILL

  ACTION EXPIRING' => [
    'EN' => 'will expire soon, ask your doctor for a new Rx',
    'ES' => ''
  ],
  'ACTION LAST REFILL' => [
    'EN' => 'has no more refills',
    'ES' => ''
  ],
  'ACTION NO REFILLS' => [
    'EN' => 'is out of refills, contact your doctor',
    'ES' => ''
  ],
  'ACTION EXPIRED' => [
    'EN' => 'has expired, ask your doctor for a new Rx',
    'ES' => ''
  ],
  'ACTION EXPIRING' => [
    'EN' => 'will expire soon, ask your doctor for a new Rx',
    'ES' => ''
  ],
  'ACTION CHECK BACK' => [
    'EN' => 'is unavailable for new RXs at this time, check back later',
    'ES' => ''
  ],
  'ACTION RX OFF AUTOFILL' => [
    'EN' => 'has autorefill turned off, request 2 weeks in advance',
    'ES' => ''
  ],
  'ACTION PATIENT OFF AUTOFILL' => [
    'EN' => 'was not filled because you have turned all medications off autorefill',
    'ES' => ''
  ],
  'ACTION NEEDS FORM' => [
    'EN' => 'can be filled once you register',
    'ES' => ''
  ]
];
