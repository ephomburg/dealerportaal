# Werkafspraken — dealerportaal

## DEPLOY: nooit zonder expliciete toestemming

`main` deployt automatisch naar de **live** site (GitHub Actions → rsync).
Daarom:

- **Standaardwerkwijze:** wijziging maken → testen in **Local** → aan de
  gebruiker rapporteren wat er getest is → **wachten** → pas `git push`
  (of `git commit` op `main`) nadat de gebruiker voor díé wijziging
  expliciet "zet live" / "push maar" / "akkoord voor live" zegt.
- **"pak op", "akkoord", "perfect", "ga door" = bouwen en testen in Local.**
  Dat is GEEN toestemming om te pushen of te deployen.
- Bij twijfel: niet pushen, eerst vragen.
- Lokaal committen op een **feature-branch** (niet `main`) mag wel zonder
  te vragen; alleen `main` + push vereist expliciete toestemming.
- `workflow_dispatch` / handmatige deploys draaien ook = zelfde regel.

## Testen gebeurt in Local

- Local-site: **"Oud - Dealerportaal"** (`homburg-dealerportaal.local`) —
  dit is de site die bij deze repo hoort. Niet de tweede site "Dealerportaal".
- wp-cli: `.\wp.ps1 <args>` vanuit de repo-root. Voor PHP met `|`/quotes:
  schrijf een `.php`-bestand en draai `.\wp.ps1 eval-file <pad>`.
- WordPress-root = `app/public/`; de repo-root ligt één niveau hoger.

## Deploy-pipeline (ter info)

- Push naar `main` die `app/public/wp-content/plugins/homburg-dealerportaal/**`
  of `app/public/wp-content/themes/homburg-dealerportaal/**` of
  `.github/workflows/deploy.yml` raakt → deploy van **plugin + thema** via
  rsync (`--delete`) naar de live server. Niets anders wordt geraakt.
- Server: cPanel, host `109.71.54.11`, user `homburgd`, poort `2222`,
  webroot `/home/homburgd/public_html/`. Deploy-sleutel: `.deploy/deploy_key`
  (gitignored).
- Huisstijl: elke afgeronde wijziging krijgt een eigen versiebump in de
  plugin-header + een `CHANGELOG.md`-regel.

## Over de gebruiker

Geen ontwikkelaar — leg dingen in gewone taal uit, geef een advies i.p.v.
een lange afweging, en benoem het als iets de live site zou kunnen raken.
