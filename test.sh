#!/usr/bin/env bash

touch siege.log
cat /dev/null > siege.log

#curl http://localhost:8080/index.php?action=init -H "Accept: application/json"

curl http://localhost:8080/index.php?action=clear -H "Accept: application/json"

echo ================ C10 ================
sudo siege -b -c10 -t70s -lsiege.log -flinks.cnf
echo ================ C25 ================
sudo siege -b -c25 -t70s -lsiege.log -flinks.cnf
echo ================ C50 ================
sudo siege -b -c50 -t70s -lsiege.log -flinks.cnf
echo ================ C100 ================
sudo siege -b -c100 -t70s -lsiege.log -flinks.cnf

curl http://localhost:8080/index.php?action=clear -H "Accept: application/json"

echo ================ C10 ================
sudo siege -b -c10 -t70s -lsiege.log -flinks-cache.cnf
echo ================ C25 ================
sudo siege -b -c25 -t70s -lsiege.log -flinks-cache.cnf
echo ================ C50 ================
sudo siege -b -c50 -t70s -lsiege.log -flinks-cache.cnf
echo ================ C100 ================
sudo siege -b -c100 -t70s -lsiege.log -flinks-cache.cnf