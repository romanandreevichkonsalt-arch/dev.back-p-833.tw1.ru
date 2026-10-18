# Бэкап staging (api_dev_fabr_usr) + медиатека

Сервер: **161.104.62.214** · пользователь **`api_dev_fabr_usr`**

Главное — медиа (~8,8 GB):

```text
/var/www/api_dev_fabr_usr/data/shared/fabrica-uploads/
```

Полезно сохранить отдельно (маленькие файлы):

```text
/var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/.env
```

Код API можно заново выкатить деплоем; **`vendor/` и `runtime/` в бэкап не обязательны**.

---

Проверка локальной копии (должно совпасть с VPS ~8,8G и ~6648 файлов):

```bash
du -sh /path/to/fabrica-uploads
find /path/to/fabrica-uploads -type f | wc -l
```

Пример: `/home/vi/Документы/fabrica-uploads`.

---

## Способ 1 — скачать файлы без SSH-ключа

**Важно:** пароль из FastPanel («Доступ к сайту») — для **FTP / MySQL / phpMyAdmin**, не для входа по **SSH/SFTP на порту 22**. На этом VPS для `api_dev_fabr_usr` sshd отвечает только **`publickey`** (проверено: `Authentications that can continue: publickey`). Поэтому PhpStorm / «Remote SSH» с типом **Password** и FileZilla **SFTP + пароль** не подключатся, даже если пароль верный.

Варианты:

1. **FastPanel → Файловый менеджер** (вход под пользователем сайта) → `shared/fabrica-uploads` — скачивание папок через браузер (медленно, но без ключей).
2. **FTP** (если в панели включён FTP для сайта): FileZilla, протокол **FTP** (не SFTP), порт **21**, тот же логин/пароль.
3. После **способа 2a** (ключ в `authorized_keys`) — SFTP/SSH с ключом `~/.ssh/id_ed25519`.

Локально: например `~/backups/fabrica-media/fabrica-uploads/` + **`app/api.dev-fabrika.ru/.env`**.

Долго (часы), но надёжно; **на сервере место не нужно**.

---

## Способ 2 — с вашего ПК через SSH (архив одним файлом)

### 2a. Починить ключ (один раз, консоль **root** Selectel)

**Не вводите команды по одной строке** — веб-консоль режет длинные строки, ломает ключ и оставляет незакрытую `'` → приглашение **`>`** (shell ждёт конец строки). Если застряли: **Ctrl+C**, снова `root@...#`.

**Проще всего** — скопировать ключ с production. Если консоль Selectel **ломает** имя `authorized_keys` при вставке, не пишите его в destination — только **`auth*`** и **`.`**:

```bash
mkdir -p /var/www/api_dev_fabr_usr/data/.ssh
cd /var/www/api_dev_fabr_usr/data/.ssh
rm -f authorizedK*
cp /var/www/api_mf_anna__usr/data/.ssh/auth* .
chmod 600 auth*
chmod 700 ..
chown -R api_dev_fabr_usr:api_dev_fabr_usr /var/www/api_dev_fabr_usr/data/.ssh
ls -la
```

(В `ls` один файл **`authorized_keys`**, владелец `api_dev_fabr_usr`.)

Альтернатива (heredoc, одним вставлением блока целиком):

```bash
DEV=/var/www/api_dev_fabr_usr/data
mkdir -p "$DEV/.ssh"
grep -q fabrica-tania "$DEV/.ssh/authorized_keys" 2>/dev/null || cat >> "$DEV/.ssh/authorized_keys" <<'EOF'
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAINSkqmZGsEfD+yKJuVRYVH0LLlIIV1xwnXgevLDLj2LF fabrica-tania
EOF
chown -R api_dev_fabr_usr:api_dev_fabr_usr "$DEV/.ssh"
chmod 700 "$DEV/.ssh"
chmod 600 "$DEV/.ssh/authorized_keys"
```

Проверка на ПК: `ssh -o IdentitiesOnly=yes -i ~/.ssh/id_ed25519 api_dev_fabr_usr@161.104.62.214 'echo ok'`

### 2b. Только медиатека

```bash
cd /path/to/fabrica-beget
./deploy/backup-media-to-local.sh
```

Архив: `~/backups/fabrica-media/fabrica-uploads-*.tar.gz`

### 2c. Медиа + `.env` (без полного `data/`)

```bash
mkdir -p ~/backups/fabrica-dev
ssh -o IdentitiesOnly=yes -i ~/.ssh/id_ed25519 api_dev_fabr_usr@161.104.62.214 \
  'tar cf - -C /var/www/api_dev_fabr_usr/data/shared fabrica-uploads -C /var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru .env' \
  | gzip -1 > ~/backups/fabrica-dev/staging-media-and-env-$(date +%Y%m%d).tar.gz
```

Поток **на ПК** — на диске VPS второй копии не создаёт.

---

## Способ 3 — root: стрим на ваш ПК (если ключ staging не работает)

На **вашем ПК** (пароль root из Selectel → «Сгенерировать новый»):

```bash
mkdir -p ~/backups/fabrica-dev
ssh -o IdentitiesOnly=yes -o PubkeyAuthentication=no root@161.104.62.214 \
  'tar cf - -C /var/www/api_dev_fabr_usr/data/shared fabrica-uploads' \
  | gzip -1 > ~/backups/fabrica-dev/fabrica-uploads-$(date +%Y%m%d).tar.gz
```

Проверка: `gzip -t ~/backups/fabrica-dev/*.tar.gz && ls -lh ~/backups/fabrica-dev/`

---

## После бэкапа

```bash
find ~/backups -name '*.tar.gz' -exec ls -lh {} \;
```

На сервере (root): `du -sh /var/www/api_dev_fabr_usr/data/shared/fabrica-uploads` — должно оставаться **~8,8G**, пока сами не удалите.

**Не делайте** `tar.gz` **на сервере** при заполненном диске (100%) — только стрим на ПК или SFTP.
