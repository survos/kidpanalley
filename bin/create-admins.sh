#!/usr/bin/env bash

bin/console survos:user:create  tacman@gmail.com tt
bin/console survos:user:create tt@survos.com tt --roles ROLE_ADMIN --roles ROLE_SUPER_ADMIN
bin/console survos:user:create paul@kidpanalley.org alanrowoth6 --roles ROLE_ADMIN
