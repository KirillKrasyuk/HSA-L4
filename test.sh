#!/usr/bin/env bash

touch siege.log
cat /dev/null > siege.log

echo ================ C10 ================
sudo siege -d1 -r10 -c10 -lsiege.log -flinks.cnf
echo ================ C25 ================
sudo siege -d1 -r10 -c25 -lsiege.log -flinks.cnf
echo ================ C50 ================
sudo siege -d1 -r10 -c50 -lsiege.log -flinks.cnf
echo ================ C100 ================
sudo siege -d1 -r10 -c100 -lsiege.log -flinks.cnf