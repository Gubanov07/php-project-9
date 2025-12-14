PORT ?= 8000

install:
	composer install

start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public

setup: install

lint:
	composer exec --verbose phpcs -- --standard=PSR12 src public

lint-fix:
	composer exec --verbose phpcbf -- --standard=PSR12 src public

compose:
	docker-compose up

compose-bash:
	docker-compose run web bash

compose-setup: compose-build
	docker-compose run web make setup

compose-build:
	docker-compose build

compose-down:
	docker-compose down -v