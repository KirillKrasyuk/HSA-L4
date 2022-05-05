## Start the stack with docker compose

```bash
$ ./run.sh
```

## Stop the stack with docker compose

```bash
$ ./stop.sh
```

## Run siege tests

```text
sudo apt-get install siege
```

```bash
$ ./test.sh
```

## Feel data

```text
GET http://localhost:8080/index.php?action=init
```

## Clear data

```text
GET http://localhost:8080/index.php?action=clear
```

## Get data

```text
GET http://localhost:8080/index.php
```