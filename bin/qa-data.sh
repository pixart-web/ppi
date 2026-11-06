#!/usr/bin/env bash
# Testes de dados: idempotência da importação, edição local, reinstalação do plugin, volume.
set -uo pipefail
cd "$(dirname "$0")/.."
if docker compose version >/dev/null 2>&1; then COMPOSE="docker compose"; else COMPOSE="docker-compose"; fi
wp() { $COMPOSE run --rm -T cli wp "$@" 2>/dev/null; }
count() { wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type=\"ppi_property\""), " imóveis · ", $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type=\"attachment\""), " media";'; }
echo "== Antes: $(count)"
echo "== 2.ª importação (idempotência)"; wp ppi import /migration/properties.json --apply --batch=0 --report=/tmp/r.json | grep -E 'APLICADO'
echo "== Depois: $(count)"
echo "== Edição local preservada"
ID=$(wp eval 'echo PPI\Rest::id_by_ref("10409","pt");')
wp eval "wp_update_post(['ID'=>$ID,'post_title'=>'Título editado localmente']);" >/dev/null
wp eval "\$f=json_decode(file_get_contents('/migration/properties.json'),true);foreach(\$f['properties'] as &\$p){if(\$p['reference']==='10409'){\$p['price']=341000;}}file_put_contents('/tmp/x.json',json_encode(\$f));" >/dev/null
$COMPOSE run --rm -T cli sh -c "wp eval \"\\\$f=json_decode(file_get_contents('/migration/properties.json'),true);foreach(\\\$f['properties'] as &\\\$p){if(\\\$p['reference']==='10409'){\\\$p['price']=341000;}}file_put_contents('/tmp/x.json',json_encode(\\\$f));\" && cp /migration/media-index.json /migration/media-review.json /tmp/ && wp ppi import /tmp/x.json --apply --batch=0 --report=/tmp/r.json" 2>/dev/null | grep -E 'APLICADO'
wp eval "echo get_the_title($ID),' · preço ',get_post_meta($ID,'ppi_price',true),' · fonte pendente: ',(get_post_meta($ID,'_ppi_source_pending',true)?'sim':'não'),PHP_EOL;"
wp eval "\$s=json_decode(get_post_meta($ID,'_ppi_source_values',true),true);wp_update_post(['ID'=>$ID,'post_title'=>\$s['title']]);delete_post_meta($ID,'_ppi_local_edit');delete_post_meta($ID,'_ppi_source_pending');update_post_meta($ID,'_ppi_import_snapshot','');" >/dev/null
echo "== Desativar/reativar plugin e tema não perde dados"
wp plugin deactivate ppi-core >/dev/null; wp plugin activate ppi-core >/dev/null; echo "Depois: $(count)"
echo "== Volume"; $COMPOSE run --rm -T -v "$PWD/tools/tests:/qa:ro" cli wp eval-file /qa/volume.php 2>/dev/null
