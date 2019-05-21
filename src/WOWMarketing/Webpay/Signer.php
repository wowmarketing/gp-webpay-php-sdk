<?php

namespace WOWMarketing\Webpay;

class Signer {

  /** @var string */
  private $privateKey;

  /** @var resource */
  private $privateKeyResource;

  /** @var string */
  private $privateKeyPassword;

  /** @var string */
  private $publicKey;

  /** @var resource */
  private $publicKeyResource;

  public function __construct (string $privateKey, string $privateKeyPassword, string $publicKey) {
    if (!file_exists($privateKey) || !is_readable($privateKey)) {
      throw new SignerException("Private key ({$privateKey}) not exists or not readable!");
    }

    if (!file_exists($publicKey) || !is_readable($publicKey)) {
      throw new SignerException("Public key ({$publicKey}) not exists or not readable!");
    }

    $this->privateKey = $privateKey;
    $this->privateKeyPassword = $privateKeyPassword;
    $this->publicKey = $publicKey;
  }

  /**
   * @return resource
   * @throws SignerException
   */
  private function getPrivateKeyResource () {
    if ($this->privateKeyResource) {
      return $this->privateKeyResource;
    }

    $key = file_get_contents($this->privateKey);

    if (!($this->privateKeyResource = openssl_pkey_get_private($key, $this->privateKeyPassword))) {
      throw new SignerException("'{$this->privateKey}' is not valid PEM private key (or passphrase is incorrect).");
    }

    return $this->privateKeyResource;
  }

  /**
   * @param array $params
   * @return string
   */
  public function sign (array $params): string {
    $digestText = implode('|', $params);
    openssl_sign($digestText, $digest, $this->getPrivateKeyResource());
    $digest = base64_encode($digest);

    return $digest;
  }

  /**
   * @param array $params
   * @param string $digest
   * @return bool
   * @throws SignerException
   */
  public function verify (array $params, $digest) {
    $data = implode('|', $params);
    $digest = base64_decode($digest);

    $ok = openssl_verify($data, $digest, $this->getPublicKeyResource());

    if ($ok !== 1) {
      throw new SignerException("Digest is not correct!");
    }

    return true;
  }

  /**
   * @return resource
   * @throws SignerException
   */
  private function getPublicKeyResource () {
    if ($this->publicKeyResource) {
      return $this->publicKeyResource;
    }

    $fp = fopen($this->publicKey, "r");
    $key = fread($fp, filesize($this->publicKey));
    fclose($fp);

    if (!($this->publicKeyResource = openssl_pkey_get_public($key))) {
      throw new SignerException("'{$this->publicKey}' is not valid PEM public key.");
    }

    return $this->publicKeyResource;
  }
}
