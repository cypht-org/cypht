
.PHONY: docker-up
docker-up:  ## start docker stack in foreground
	docker compose up --build --abort-on-container-exit

.PHONY: docker-push
.ONESHELL:
docker-push:  ## build, tag, and push image to dockerhub. presumes you are logged in
	username=$$(docker info | sed '/Username:/!d;s/.* //')
	tag=latest	# TODO: set from argument
	docker buildx build . --platform linux/amd64 -t $${username}/cypht:$${tag} -f docker/Dockerfile --push
	# TODO: build for arm architectures

.PHONY: test-unit
test-unit:	## locally run the unit tests
	cd tests/phpunit/ && phpunit && cd ../../
	# TODO: how are local tests supposed to run? see https://github.com/cypht-org/cypht/issues/1011

.PHONY: setup
.ONESHELL:
setup:  ## locally setup app and users. presumes env vars are set
	set -e
	echo "Installing dependencies"
	composer install
	echo "Creating tables and user"
	./scripts/setup_database.php
	echo "Creating directories and configs"
	./scripts/setup_system.sh

help:  ## get help
	@grep -E '^[a-zA-Z_-]+:.*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
