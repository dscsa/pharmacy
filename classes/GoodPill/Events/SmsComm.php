<?php

namespace GoodPill\Events;

use GoodPill\Events\Comm;

class SmsComm extends Comm
{
    protected $properties = [
        'message',
        'sms',
        'fallbacks'
    ];

    protected $required = [
        'message',
        'sms',
    ];

    /**
     * Create a Comm Calendar compatible Delivery message
     * @return array
     */
    public function delivery() : array
    {

        // Make sure all the required fields are complete
        if (! $this->requiredFieldsComplete()) {
            throw new \Exception('Missing required fields');
        }

        /**
         * Create the fallback message
         */

        // Make some substitutions that help with make calls clearer
        $regex = [
            '/View it at [^ ]+ /',
            '/Track it at [^ ]+ /',
            '/\(?888[)-.]? ?987[.-]?5187/',
            '/(www\.)?goodpill\.org/',
            '/(\w):(?!\/\/)/',
            '/;<br>/',
            '/;/',
            '/\./',
            '/(<br>)+/',
            '/\.(\d)(\d)?(\d)?/',
            '/ but /',
            '/(\d+)MG/',
            '/(\d+)MCG/',
            '/(\d+)MCG/',
            '/ Rxs/i',
            '/ ER /i',
            '/ DR /i',
            '/ TAB| CAP/i',
            '/\#(\d)(\d)(\d)(\d)(\d)(\d)?/'
          ];

        $replace = [
            "",
            "View and track your order online at www.goodpill.org",
            '8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7',
            'w,,w,,w,,dot,,,,good,,,,pill,,,,dot,,,,org,,,,again that is g,,,,o,,,,o,,,d,,,,p,,,,i,,,,l,,,,l,,,,dot,,,,o,,,,r,,,,g',
            '$1<Pause />', //Don't capture JSON $text or URL links
            ';<Pause /> and <Pause />', //combine drug list with "and" since it sounds more natural.  Keep semicolon so regex can still find and remove.
            ';<Pause />', //can't do commas without testing for inside quotes because that is part of json syntax. Keep semicolon so regex can still find and remove.
            ' <Pause />', //can't do commas without testing for inside quotes because that is part of json syntax
            ' <Pause length="1" />',
            ' point $1,,$2,,$3', //skips pronouncing decimal points
            ',,,,but,,,,',
            '<Pause />$1 milligrams',
            '<Pause />$1 micrograms',
            '<Pause />$1 micrograms',
            ' prescriptions',
            ' extended release ',
            ' delayed release ',
            ' <Pause />',
            'number,,,,$1,,$2,,$3,,$4,,$5,,$6' //<Pause /> again that is $order number <Pause />$1,,$2,,$3,,$4,,$5,,$6
          ];

        $fallback_message = preg_replace($regex, $replace, $this->message);
        $fallback_message = 'Hi, this is Good Pill Pharmacy <Pause />'
                         . $fallback_message
                         . ' <Pause length="2" />if you need to speak to someone please call us at '
                         . '8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7. <Pause '
                         . 'length="2" /> Again our phone number is 8,,,,8,,,,8 <Pause />9,,,,8,,,,7'
                         . ' <Pause />5,,,,1,,,,8,,,,7. <Pause />';

        $this->fallbacks = [
            'message' => $fallback_message,
            'call' => $this->sms
        ];

        return $this->toArray();
    }
}
