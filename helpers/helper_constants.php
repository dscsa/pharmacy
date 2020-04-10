<?php

const LIVE_MODE = true;

const DAYS_STD = 90;

const ADDED_MANUALLY = [
  "MANUAL",
  "WEBFORM"
];

const PICK_LIST_FOLDER_NAME = 'Pick Lists';
const INVOICE_PUBLISHED_FOLDER_NAME = 'Published';  //Published
const INVOICE_PENDING_FOLDER_NAME   = 'Pending';  //Published


const PAYMENT_TOTAL_NEW_PATIENT = 6;

const PAYMENT_METHOD = [
  'COUPON'       => 'coupon',
  'MAIL'         => 'cheque',
  'ONLINE'       => 'cod',
  'AUTOPAY'      => 'stripe',
  'CARD EXPIRED' => 'stripe-card-expired'
];

const ORDER_STATUS_WC = [

  'confirm-transfer' => 'Confirming Order (Transfer)',
  'confirm-refill'   => 'Confirming Order (Refill Request)',
  'confirm-autofill' => 'Confirming Order (Autofill)',
  'confirm-new-rx'   => 'Confirming Order (Doctor Will Send Rxs)',

  'prepare-refill'    => 'Preparing Order (Refill)',
  'prepare-erx'       => 'Preparing Order (eScript)',
  'prepare-fax'       => 'Preparing Order (Fax)',
  'prepare-transfer'  => 'Preparing Order (Transfer)',
  'prepare-phone'     => 'Preparing Order (Phone)',
  'prepare-mail'      => 'Preparing Order (Mail)',

  'shipped-mail-pay' => 'Shipped (Pay by Mail)',
  'shipped-auto-pay' => 'Shipped (Autopay Scheduled)',
  'shipped-web-pay'  => 'Shipped (Pay Online)',
  'shipped-part-pay' => 'Shipped (Partially Paid)',

  'done-card-pay'    => 'Completed (Paid by Card)',
  'done-mail-pay'    => 'Completed (Paid by Mail)',
  'done-finaid'      => 'Completed (Financial Aid)',
  'done-fee-waived'  => 'Completed (Fee Waived)',
  'done-clinic-pay'  => 'Completed (Paid by Clinic)',
  'done-auto-pay'    => 'Completed (Paid by Autopay)',
  'done-refused-pay' => 'Completed (Refused to Pay)',

  'late-mail-pay'     => 'Shipped (Mail Payment Not Made)',
  'late-card-missing' => 'Shipped (Autopay Card Missing)',
  'late-card-expired' => 'Shipped (Autopay Card Expired)',
  'late-card-failed'  => 'Shipped (Autopay Card Failed)',
  'late-web-pay'      => 'Shipped (Online Payment Not Made)',
  'late-payment-plan' => 'Shipped (Payment Plan Approved)',

  'return-usps'      => 'Returned (USPS)',
  'return-customer'  => 'Returned (Customer)'
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
    'ES' => '',
    'CP_CODE' => 200,
  ],
  'NO ACTION PAST DUE AND SYNC TO ORDER' => [
    'EN' => 'is past due so synced to Order',
    'ES' => '',
    'CP_CODE' => 201,
  ],
  'NO ACTION NO NEXT AND SYNC TO ORDER' => [
    'EN' => 'was past due so synced to Order',
    'ES' => '',
    'CP_CODE' => 202,
  ],
  'NO ACTION DUE SOON AND SYNC TO ORDER' => [
    'EN' => 'is due soon so synced to Order',
    'ES' => '',
    'CP_CODE' => 203,
  ],
  'NO ACTION NEW RX SYNCED TO ORDER' => [
    'EN' => 'is a new Rx synced to Order',
    'ES' => '',
    'CP_CODE' => 204,
  ],
  'NO ACTION SYNC TO DATE' => [
    'EN' => 'was synced to refill_target_date',
    'ES' => '',
    'CP_CODE' => 205,
  ],
  'NO ACTION RX OFF AUTOFILL' => [
    'EN' => 'was requested',
    'ES' => '',
    'CP_CODE' => 206,
  ],
  'NO ACTION RECENT FILL' => [
    'EN' => 'filled on refill_date_last and due on refill_date_next',
    'ES' => '',
    'CP_CODE' => 207,
  ],
  'NO ACTION NOT DUE' => [
    'EN' => 'is not due until refill_date_next',
    'ES' => '',
    'CP_CODE' => 208,
  ],
  'NO ACTION CHECK SIG' => [
    'EN' => 'was prescribed in an unusually high qty and needs to be reviewed by a pharmacist',
    'ES' => '',
    'CP_CODE' => 209,
  ],
  'NO ACTION MISSING GSN' => [
    'EN' => "is being checked if it's available",
    'ES' => '',
    'CP_CODE' => 210,
  ],
  'NO ACTION NEW GSN' => [
    'EN' => 'is being verified',
    'ES' => '',
    'CP_CODE' => 211,
  ],
  'NO ACTION LOW STOCK' => [
    'EN' => 'is low in stock',
    'ES' => '',
    'CP_CODE' => 212,
  ],
  'NO ACTION LOW REFILL' => [
    'EN' => 'has limited refills',
    'ES' => '',
    'CP_CODE' => 213,
  ],
  'NO ACTION WILL TRANSFER CHECK BACK' => [
    'EN' => 'will be transferred to your local pharmacy. Check back in 3 months',
    'ES' => '',
    'CP_CODE' => 214,
  ],
  'NO ACTION WILL TRANSFER' => [
    'EN' => 'is not offered and will be transferred to your local pharmacy',
    'ES' => '',
    'CP_CODE' => 215,
  ],
  'NO ACTION WAS TRANSFERRED' => [
    'EN' => 'was transferred out to your local pharmacy on rx_date_changed',
    'ES' => '',
    'CP_CODE' => 216,
  ],
  'NO ACTION PATIENT OFF AUTOFILL' => [
    'EN' => 'was requested',
    'ES' => '',
    'CP_CODE' => 217,
  ],

  //ACTION BY USER REQUIRED BEFORE (RE)FILL

  'ACTION EXPIRING' => [
    'EN' => 'will expire soon, ask your doctor for a new Rx',
    'ES' => '',
    'CP_CODE' => 100,
  ],
  'ACTION LAST REFILL' => [
    'EN' => 'is the last refill, contact your doctor',
    'ES' => '',
    'CP_CODE' => 101,
  ],
  'ACTION NO REFILLS' => [
    'EN' => 'is out of refills, contact your doctor',
    'ES' => '',
    'CP_CODE' => 102,
  ],
  'ACTION EXPIRED' => [
    'EN' => 'will expire before your next refill, ask your doctor for a new Rx',
    'ES' => '',
    'CP_CODE' => 103,
  ],
  'ACTION CHECK BACK' => [
    'EN' => 'is unavailable for new RXs at this time, check back later',
    'ES' => '',
    'CP_CODE' => 104,
  ],
  'ACTION RX OFF AUTOFILL' => [
    'EN' => 'has autorefill turned off, request 2 weeks in advance',
    'ES' => '',
    'CP_CODE' => 105,
  ],
  'ACTION PATIENT OFF AUTOFILL' => [
    'EN' => 'was not filled because you have turned all medications off autorefill',
    'ES' => '',
    'CP_CODE' => 106,
  ],
  'ACTION NEEDS FORM' => [
    'EN' => 'can be filled once you register',
    'ES' => '',
    'CP_CODE' => 107,
  ]
];
