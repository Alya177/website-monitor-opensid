# website-monitor-opensid
Scrip untuk memonitor website dan melakukan reboot jika website tidak bisa diakses

Sesuaikan website yang akan di monitor didalam script auto_reboot.php

Pengecekan dilakukan dijam operasional seperti tertera dalam script

Scipt ini membantu jika website yang berbasis opensid crash dikarenakan kelebihan beban dll dan perlu melakukan reboot

Diperlukan schedule task tambahan dalam cronjob untuk memicu script ini aktif

Pastekan tugas berikut kedalam cronjob/crontab
buka terminal
```
crontab -e
```
Dalam hal ini file auto_reboot.php berada di directory "/root" sesuaikan dengan direktory sebenarnya

```
@reboot sleep 300 && nohup php /root/auto_reboot.php > /root/auto_reboot_nohup.log 2>&1 &
```
