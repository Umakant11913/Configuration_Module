<?php

namespace App\Services;

use Spatie\Crypto\Rsa\PrivateKey;
use Spatie\Crypto\Rsa\PublicKey;

class CryptService
{

    public function generateKeyPair()
    {
        $dn = array("countryName" => 'IN', "stateOrProvinceName" => 'State', "localityName" => 'SomewhereCity', "organizationName" => 'MySelf', "organizationalUnitName" => 'Whatever', "commonName" => 'mySelf', "emailAddress" => 'user@domain.com');
        $numberofdays = 365;

        $privkey = openssl_pkey_new();
        $csr = openssl_csr_new($dn, $privkey);
        $sscert = openssl_csr_sign($csr, null, $privkey, $numberofdays);
        openssl_x509_export($sscert, $publickey);
        openssl_pkey_export($privkey, $privatekey);
        openssl_csr_export($csr, $csrStr);

//        echo $privatekey; // Exported PriKey
//        echo $publickey;  // Exported PubKey
//        echo $csrStr;     // Exported Certificate Request
        return [$privatekey, $publickey];
    }

    public function encryptUsingPublicKey($data, $publicKey, $isX509 = true)
    {
        if ($isX509) {
            $publicData = openssl_pkey_get_details(openssl_pkey_get_public($publicKey));
            $publicKey = $publicData['key'];
        }
        $pubKey = PublicKey::fromString($publicKey);
        return $pubKey->encrypt($data);
    }

    public function decryptUsingPrivateKey($data, $privateKey)
    {
        $pubKey = PrivateKey::fromString($privateKey);
        return $pubKey->decrypt($data);
    }

    public function encryptUsingPrivateKey($data, $privateKey)
    {
        $privKey = PrivateKey::fromString($privateKey);
        return $privKey->encrypt($data);
    }

    public function encryptUsingPrivateKeyFile($data, $privateKey, $password = null)
    {
        $privKey = PrivateKey::fromFile($privateKey, $password);
        return $privKey->encrypt($data);
    }

    public function decryptUsingPublicKey($data, $publicKey, $isX509 = true)
    {
        if ($isX509) {
            $publicData = openssl_pkey_get_details(openssl_pkey_get_public($publicKey));
            $publicKey = $publicData['key'];
        }
        $pubKey = PublicKey::fromString($publicKey);
        return $pubKey->decrypt($data);
    }

    public function chunkAndEncryptUsingPrivateKey($data, $privateKey, $separator = '', $chunkSize = 245)
    {
        $chunks = str_split($data, $chunkSize);
        $base64Array = [];
        foreach ($chunks as $chunk) {
            $cipher = $this->encryptUsingPrivateKey($chunk, $privateKey);
            $base64Array[] = base64_encode($cipher);
        }
        return implode($separator, $base64Array);
    }

    public function chunkAndEncryptUsingPrivateKeyFromFile($data, $privateKeyFile, $password = null, $separator = '', $chunkSize = 245)
    {
        $chunks = str_split($data, $chunkSize);
        $base64Array = [];
        foreach ($chunks as $chunk) {
            $cipher = $this->encryptUsingPrivateKeyFile($chunk, $privateKeyFile, $password);
//            $base64Array[] = base64_encode($cipher);
            $base64Array[] = $cipher;
        }
//        dd($base64Array);
        return implode($separator, $base64Array);
    }

    public function chunkAndDecryptUsingPublicKey($data, $publicKey, $separator = '', $chunkSize = 256)
    {
        $cipherTextChunk = base64_decode($data);
//        $cipherArray = explode($separator, $cipherTextChunk);
        $cipherArray = str_split($cipherTextChunk, $chunkSize);
        $decryptedBase64Array = [];
//        dd($cipherArray);
        foreach ($cipherArray as $cipher) {
//            $cipher = base64_decode($base64string);
            $decryptedBase64Array[] = $this->decryptUsingPublicKey($cipher, $publicKey);
        }
        return implode("", $decryptedBase64Array);
    }

    public function base64UrlEncode($input)
    {
        return strtr(base64_encode($input), '+/=', '._-');
    }

    function base64UrlDecode($input)
    {
        return base64_decode(strtr($input, '._-', '+/='));
    }
}
