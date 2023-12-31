.PHONY: keygen
keygen:
	cd keys ; \
	openssl ecparam -genkey -name prime256v1 -noout -out private.pem ; \
	openssl ec -in private.pem -outform DER | tail -c +8 | head -c 32 | base64 | tr -d = | tr /+ _- > private.key ; \
	openssl ec -in private.pem -pubout -outform DER | tail -c 65 | base64 | tr -d = | tr /+ _- > public.key

.PHONY: composer
composer:
	docker run --rm -ti -v $${PWD}:/app composer:2.1.11 composer install

.PHONY: fix
fix:
	docker run --rm -ti -v $${PWD}:/app composer:2.1.11 vendor/bin/php-cs-fixer fix

.PHONY: check
check:
	docker run --rm -ti -v $${PWD}:/app composer:2.1.11 vendor/bin/php-cs-fixer fix --dry-run --diff

.PHONY: analyze
analyze:
	docker run --rm -ti -v $${PWD}:/app composer:2.1.11 vendor/bin/phpstan analyse --verbose --memory-limit=4G
