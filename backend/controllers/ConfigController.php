<?php

class ConfigController {
    public function getConfig() {
        echo json_encode([
            "success" => true,
            "config" => [
                "paystackPublicKey" => Env::get('PAYSTACK_PUBLIC_KEY', '')
            ]
        ]);
        exit;
    }
}
?>
