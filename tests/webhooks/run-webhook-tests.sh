#!/usr/bin/env bash
# Acceptance tests for the webhooks (media webhook and per-webhook tokens).
# Runs the webhooks with PHP's built-in server against a temporary files
# folder: no database, no Docker.
#
#   bash tests/webhooks/run-webhook-tests.sh
set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="$(mktemp -d)"
PORT_A=18471   # fully configured
PORT_B=18472   # media token missing
PORT_D=18474   # media configured, delete left disabled
PASS=0; FAIL=0
KEY=1234567890

cleanup() { kill "${PID_A:-}" "${PID_B:-}" "${PID_D:-}" 2>/dev/null; rm -rf "$WORK"; }
trap cleanup EXIT

mkdir -p "$WORK/files/praps2/media/1" "$WORK/files/praps2/media/2/sub" "$WORK/files/sondage_audio" \
         "$WORK/files/apps" "$WORK/files/other" "$WORK/var/logs" "$WORK/var/cache"
head -c 2000 /dev/urandom > "$WORK/files/praps2/media/2/sub/9999999999_ENTRETIEN.m4a"
head -c 2000 /dev/urandom > "$WORK/files/sondage_audio/SONDAGE_0001.m4a"
echo app > "$WORK/files/apps/app.pen"
head -c 300000 /dev/urandom > "$WORK/files/praps2/media/1/${KEY}_ENTRETIEN.m4a"
head -c 5000   /dev/urandom > "$WORK/files/praps2/media/1/${KEY}_AMENAGEMENT.jpg"
head -c 1000   /dev/urandom > "$WORK/files/praps2/media/1/${KEY}_RESUME.m4a"
echo '<?php echo 1;'       > "$WORK/files/praps2/media/1/evil.php"
head -c 100    /dev/urandom > "$WORK/files/other/secret.m4a"
ln -s "$WORK/files/other" "$WORK/files/praps2/media/escape"

MEDIA_TOKEN=media-token-test
SHARED_TOKEN=shared-token-test
STATUS_TOKEN=status-token-test

start() { # port, extra env...
  local port=$1; shift
  env -i PATH="$PATH" CSWEB_ROOT="$WORK" FILES_FOLDER="$WORK/files" \
      BREAKOUT_WEBHOOK_TOKEN="$SHARED_TOKEN" WEBHOOK_TOKEN_STATUS="$STATUS_TOKEN" \
      MEDIA_WEBHOOK_PREFIXES="praps2/media/,sondage_audio/" MEDIA_WEBHOOK_MAX_BYTES=100000 \
      WEBHOOK_RATE_LIMIT_PER_MIN=1000 "$@" \
      php -S 127.0.0.1:"$port" -t "$ROOT" >"$WORK/server-$port.log" 2>&1 &
  echo $!
}
PID_A=$(start $PORT_A WEBHOOK_TOKEN_MEDIA="$MEDIA_TOKEN" MEDIA_WEBHOOK_ALLOW_DELETE=1)
PID_B=$(start $PORT_B)
PID_D=$(start $PORT_D WEBHOOK_TOKEN_MEDIA="$MEDIA_TOKEN")
for p in $PORT_A $PORT_B $PORT_D; do
  for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$p/" && break; sleep 0.1; done
done

# call PORT TOKEN SCRIPT BODY -> sets $CODE and $BODY
call() {
  local out
  out=$(curl -s -w '\n%{http_code}' -X "${METHOD:-POST}" "http://127.0.0.1:$1/$3" \
        ${2:+-H "Authorization: Bearer $2"} -H 'Content-Type: application/json' ${4:+--data "$4"})
  CODE=${out##*$'\n'}; BODY=${out%$'\n'*}
}
json() { php -r '$d=json_decode(stream_get_contents(STDIN),true); $v=$d; foreach(explode(".",$argv[1]) as $k){ $v=is_array($v)&&array_key_exists($k,$v)?$v[$k]:null; } echo is_bool($v)?($v?"true":"false"):(is_array($v)?json_encode($v,JSON_UNESCAPED_SLASHES):$v);' "$1" <<<"$BODY"; }
check() { # name expected actual
  if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '$3')"; echo "       $BODY" | head -c 400; echo; fi
}

echo "1. List and read with the media token"
call $PORT_A $MEDIA_TOKEN media-webhook.php '{"action":"list","dossier":"praps2/media/1"}'
check "list -> 200" 200 "$CODE"
check "list shows the 3 allowed media (not evil.php)" 3 "$(json data.fichiers | php -r 'echo count(json_decode(stream_get_contents(STDIN),true));')"
check "responses carry Cache-Control: no-store" "no-store" "$(curl -s -D - -o /dev/null -X POST -H "Authorization: Bearer $MEDIA_TOKEN" --data '{"action":"list","dossier":"praps2/media/1"}' http://127.0.0.1:$PORT_A/media-webhook.php | tr -d '\r' | sed -n 's/^Cache-Control: //Ip')"
call $PORT_A $MEDIA_TOKEN media-webhook.php "{\"action\":\"read\",\"chemin\":\"praps2/media/1/${KEY}_RESUME.m4a\"}"
check "read -> 200" 200 "$CODE"
check "read sha256 matches the file" "$(sha256sum "$WORK/files/praps2/media/1/${KEY}_RESUME.m4a" | cut -d' ' -f1)" "$(json data.sha256)"
check "decoded content matches the file" "$(json data.sha256)" "$(json data.contenu | base64 -d | sha256sum | cut -d' ' -f1)"
check "type audio/mp4" "audio/mp4" "$(json data.type)"

echo "1a. Several media folders"
call $PORT_A $MEDIA_TOKEN media-webhook.php '{"action":"list"}'
check "list without folder -> allowed folders only" '["praps2/media","sondage_audio"]' "$(json data.dossiers)"
call $PORT_A $MEDIA_TOKEN media-webhook.php '{"action":"list","dossier":"sondage_audio"}'
check "second folder listed" "sondage_audio/SONDAGE_0001.m4a" "$(json data.fichiers | php -r 'echo json_decode(stream_get_contents(STDIN),true)[0]["chemin"];')"
call $PORT_A $MEDIA_TOKEN media-webhook.php '{"action":"list","dossier":"praps2/media","recursif":true}'
check "recursive list finds media in every sub-folder" 4 "$(json data.fichiers | php -r 'echo count(json_decode(stream_get_contents(STDIN),true));')"
check "recursive list does not follow the link out of the folder" 0 "$(json data.fichiers | grep -c secret)"
call $PORT_A $MEDIA_TOKEN media-webhook.php '{"action":"list","dossier":"apps"}'
check "apps folder not allowed -> 403" 403 "$CODE"

echo "1b. Large file: 413 in one piece, readable in pieces"
F="praps2/media/1/${KEY}_ENTRETIEN.m4a"
call $PORT_A $MEDIA_TOKEN media-webhook.php "{\"action\":\"read\",\"chemin\":\"$F\"}"
check "read whole 300 kB file over a 100 kB limit -> 413" 413 "$CODE"
: > "$WORK/pieces"
for off in 0 100000 200000; do
  call $PORT_A $MEDIA_TOKEN media-webhook.php "{\"action\":\"read\",\"chemin\":\"$F\",\"offset\":$off,\"longueur\":100000}"
  json data.contenu | base64 -d >> "$WORK/pieces"
done
check "pieces reassemble to the file" "$(sha256sum "$WORK/files/$F" | cut -d' ' -f1)" "$(sha256sum "$WORK/pieces" | cut -d' ' -f1)"

echo "2. Delete: off by default; when enabled, inside the prefix only"
call $PORT_D $MEDIA_TOKEN media-webhook.php "{\"action\":\"delete\",\"chemin\":\"praps2/media/1/${KEY}_AMENAGEMENT.jpg\"}"
check "delete disabled by default -> 403" 403 "$CODE"
check "delete disabled error code" "delete_disabled" "$(json error.code)"
check "file still there" 1 "$(ls "$WORK/files/praps2/media/1" | grep -c AMENAGEMENT)"
call $PORT_A $MEDIA_TOKEN media-webhook.php "{\"action\":\"delete\",\"chemin\":\"praps2/media/1/${KEY}_AMENAGEMENT.jpg\"}"
check "delete -> 200" 200 "$CODE"
check "moved to trash" "corbeille" "$(json data.mode)"
check "file in .trash" 1 "$(ls "$WORK/files/.trash" | grep -c AMENAGEMENT)"
call $PORT_A $MEDIA_TOKEN media-webhook.php "{\"action\":\"delete\",\"chemin\":\"praps2/media/1/${KEY}_RESUME.m4a\",\"definitif\":true}"
check "definitive delete -> 200" 200 "$CODE"
check "file erased" 0 "$(ls "$WORK/files/praps2/media/1" | grep -c RESUME)"
call $PORT_A $MEDIA_TOKEN media-webhook.php "{\"action\":\"delete\",\"chemin\":\"praps2/media/1/${KEY}_RESUME.m4a\"}"
check "delete missing file -> 404" 404 "$CODE"
call $PORT_A $MEDIA_TOKEN media-webhook.php '{"action":"delete","chemin":"other/secret.m4a"}'
check "delete outside prefix -> 403" 403 "$CODE"
call $PORT_A $MEDIA_TOKEN media-webhook.php '{"action":"read","chemin":"praps2/media/escape/secret.m4a"}'
check "symbolic link leaving the prefix -> 403" 403 "$CODE"

echo "3. Malformed paths -> 400"
for p in "praps2/media/../other/secret.m4a" "/etc/passwd.m4a" "praps2/media/1/evil.php" "praps2/media/./1/x.m4a" 'praps2\\media\\1\\x.m4a'; do
  call $PORT_A $MEDIA_TOKEN media-webhook.php "{\"action\":\"read\",\"chemin\":\"$p\"}"
  check "read '$p' -> 400" 400 "$CODE"
done
call $PORT_A $MEDIA_TOKEN media-webhook.php '{"action":"explode"}'
check "unknown action -> 400" 400 "$CODE"
METHOD=GET call $PORT_A $MEDIA_TOKEN media-webhook.php
check "GET -> 405" 405 "$CODE"

echo "4. Tokens"
call $PORT_B $MEDIA_TOKEN media-webhook.php '{"action":"list","dossier":"praps2/media/1"}'
check "media token not configured -> 503" 503 "$CODE"
call $PORT_B $SHARED_TOKEN media-webhook.php '{"action":"list","dossier":"praps2/media/1"}'
check "shared token never opens the media webhook -> 503" 503 "$CODE"
call $PORT_A wrong-token media-webhook.php '{"action":"list","dossier":"praps2/media/1"}'
check "wrong token -> 401" 401 "$CODE"
call $PORT_A "" media-webhook.php '{"action":"list","dossier":"praps2/media/1"}'
check "no token -> 401" 401 "$CODE"
call $PORT_A $SHARED_TOKEN media-webhook.php '{"action":"list","dossier":"praps2/media/1"}'
check "shared token on media -> 401" 401 "$CODE"
METHOD=GET call $PORT_A $SHARED_TOKEN breakout-status-webhook.php
check "status webhook refuses the shared token once WEBHOOK_TOKEN_STATUS is set -> 401" 401 "$CODE"
METHOD=GET call $PORT_A $STATUS_TOKEN breakout-status-webhook.php
check "status webhook accepts its own token (then needs a database)" "not401" "$([ "$CODE" != 401 ] && echo not401 || echo 401)"
call $PORT_A $MEDIA_TOKEN breakout-webhook.php '{"dictionary":"X"}'
check "media token does not open the breakout webhook -> 401" 401 "$CODE"
env -i PATH="$PATH" CSWEB_ROOT="$WORK" php -S 127.0.0.1:18473 -t "$ROOT" >/dev/null 2>&1 & PID_C=$!; sleep 0.5
call 18473 anything breakout-webhook.php '{"dictionary":"X"}'
check "no token configured at all -> 503" 503 "$CODE"
kill $PID_C 2>/dev/null

echo "6. No respondent key in the webhook log"
check "media-webhook.log has entries" "yes" "$([ -s "$WORK/var/logs/media-webhook.log" ] && echo yes || echo no)"
check "media-webhook.log contains no case key" 0 "$(grep -c "$KEY" "$WORK/var/logs/media-webhook.log")"

echo
echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
