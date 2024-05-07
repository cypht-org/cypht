
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
	# TODO: how are local tests supposed to run?


# TODO: make recipes or perhaps use go-task?
# add user
# start local?
# make local dirs
# setup local db
# install local requirements


help:  ## get help
	@grep -E '^[a-zA-Z_-]+:.*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
