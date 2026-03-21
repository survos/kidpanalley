bin/console import:filesystem ~/Downloads/epstein/ --probe 0 #--root-id epstein
bin/console import:convert --profile-only epstein.jsonl
#bin/console code:entity --dto data/epstein.profile.json App\\Index\\Epstein
bin/console m:settings:update --force epstein
bin/console meili:flush-file epstein epstein.jsonl -vvv

