ARGS ?=

.PHONY: phar
phar:
	php ./bin/console --env=prod cache:warmup; \
	php -d phar.readonly=false tools/box.phar compile --config=box.json

.PHONY: install-phive
install-phive:
	wget -O tools/phive.phar https://phar.io/releases/phive.phar; \
	wget -O tools/phive.phar.asc https://phar.io/releases/phive.phar.asc; \
	gpg --keyserver pool.sks-keyservers.net --recv-keys 0x9D8A98B29B2D5D79; \
	gpg --verify tools/phive.phar.asc tools/phive.phar; \
	chmod +x tools/phive.phar

.PHONY: setup
setup: install-phive
	docker build -t phpdoc/dev docker/with-xdebug; \
	docker run -it --rm -v${CURDIR}:/opt/phpdoc -w /opt/phpdoc phpdoc/dev tools/phive.phar install --force-accept-unsigned

.PHONY: phpcs
phpcs:
	docker-compose run --rm phpcs ${ARGS}

.PHONY: phpcbf
phpcbf:
	docker-compose run --rm phpcs phpcbf ${ARGS}

.PHONY: phpstan
phpstan:
	docker-compose run --rm phpstan ${ARGS}

.PHONY: test
test:
	docker-compose run --rm phpunit ${ARGS}
	docker-compose run --entrypoint=/usr/local/bin/php --rm phpunit tests/coverage-checker.php 69

.PHONY: integration-test
integration-test: node_modules/.bin/cypress build/default/index.html build/clean/index.html
	docker run -it --rm -v ${CURDIR}:/e2e -w /e2e cypress/included:3.2.0

.PHONY: behat
behat:
	docker-compose run --rm behat ./tools/behat ${ARGS}

.PHONY: pre-commit-test
pre-commit-test: phpcs phpstan test

.PHONY: shell
shell:
	CURRENT_UID=$(shell id -u):$(shell id -g) docker-compose run --rm -v ${CURDIR}:/opt/phpdoc -w /opt/phpdoc --entrypoint=/bin/bash phpdoc

node_modules/.bin/cypress:
	docker run -it --rm -v ${CURDIR}:/opt/phpdoc -w /opt/phpdoc node npm install

build/default/index.html: data/examples/MariosPizzeria/**/*
	CURRENT_UID=$(shell id -u):$(shell id -g) docker-compose run --rm -v ${CURDIR}:/opt/phpdoc phpdoc --config=data/examples/MariosPizzeria/phpdoc.xml --template=default --target=build/default

build/clean/index.html: data/examples/MariosPizzeria/**/*
	CURRENT_UID=$(shell id -u):$(shell id -g) docker-compose run --rm -v ${CURDIR}:/opt/phpdoc phpdoc --config=data/examples/MariosPizzeria/phpdoc.xml --template=clean --target=build/clean
