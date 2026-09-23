#!/usr/bin/env bash
set -euo pipefail
# Run once as root on the voice host before enabling the production editor.
source_file=/etc/aizentrum-voice/instructions.txt
shared_dir=/var/www/ai-airbagszentrum-shared/storage/app/voice
shared_file="$shared_dir/instructions.txt"
install -d -o www-data -g aizentrum-voice -m 2750 "$shared_dir"
if [[ ! -L "$source_file" ]]; then
    if [[ -e "$shared_file" ]]; then
        cmp -s "$source_file" "$shared_file" || { echo 'Instruction files differ; reconcile before linking.' >&2; exit 1; }
    else
        install -o www-data -g aizentrum-voice -m 0640 "$source_file" "$shared_file"
    fi
    cp -p "$source_file" "$source_file.before-admin-$(date -u +%Y%m%dT%H%M%SZ)"
    ln -s "$shared_file" "$source_file.admin-link"
    mv -Tf "$source_file.admin-link" "$source_file"
fi
test "$(readlink -f "$source_file")" = "$shared_file"
chown www-data:aizentrum-voice "$shared_file"
chmod 0640 "$shared_file"
sudo -u aizentrum-voice test -r "$source_file"
sudo -u www-data test -w "$shared_dir"
echo 'The admin editor and new phone calls now share the same instructions.'
