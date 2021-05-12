<?php

/**
 * Created by Reliese Model.
 */

namespace GoodPill\API;

use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface;
use GoodPill\Models\Utility\UtiNonce;

/**
 * A simple class to allow a structed format to the API response message
 */
class TokenSet
{
    public static function generate($data)
    {
        $auth  = JWT::encode(
            array_merge(
                [
                    "exp" => strtotime('+10 minute'),
                    "type"         => "auth"
                ],
                $data
            ),
            JWT_PRIVATE,
            'RS256'
        );

        // TODO create a real nonce

        $nonce = new UtiNonce();
        $nonce->generate();
        $nonce->save();

        $refresh = JWT::encode(
            array_merge(
                [
                    "exp" => strtotime('+30 day'),
                    "type"         => "auth",
                    'nonce' => $nonce->token,
                    'refresh' => 1
                ],
                $data
            ),
            JWT_PRIVATE,
            'RS256'
        );

        return [
            'auth'    => $auth,
            'refresh' => $refresh
        ];
    }
}
