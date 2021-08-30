# Install

```
git clone https://github.com/korneliusrayani/bt.git pay
cd pay && cp .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
```

## Details

URL: bt.test/login

User: admin@gmail.com

Password: 12341234

## Adding braintree Crendentials 

1. Login as admin > Go to Payment Management > Braintree
2. Fill fields: sanbox merchant ID, sandobx public key, sandobx private key
