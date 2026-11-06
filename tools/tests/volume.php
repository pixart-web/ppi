<?php
/**
 * Ensaio de volume em memória: mede pesquisa com 3000 imóveis sintéticos, sem gravar nada na base de dados.
 * Uso: wp eval-file tools/tests/volume.php
 */
$base = array_values( PPI\Search::index( 'pt' ) );
$rows = array();
for ( $i = 0; $i < 3000; $i++ ) {
	$r = $base[ $i % count( $base ) ];
	$r['id'] = 1000000 + $i;
	$r['ext'] = 7000000 + $i;
	$r['price'] = $r['price'] ? $r['price'] + $i : null;
	$rows[ $r['id'] ] = $r;
}
$ref = new ReflectionClass( PPI\Search::class );
$memo = $ref->getProperty( 'memo' );
$memo->setAccessible( true );
$memo->setValue( null, array( 'pt' => $rows ) );
$cases = array( array(), array( 'tipo' => 'moradia', 'quartos' => '3' ), array( 'ordem' => 'preco-desc', 'preco_max' => '1000000', 'carac' => 'piscina' ), array( 'local' => 'budens', 'ordem' => 'preco-asc', 'pagina' => '5' ) );
foreach ( $cases as $c ) {
	$t = microtime( true );
	for ( $k = 0; $k < 20; $k++ ) {
		$res = PPI\Search::run( PPI\Search::params( $c ) );
	}
	printf( "%-70s %5d resultados · %.1f ms/pesquisa\n", wp_json_encode( $c ), $res['total'], ( microtime( true ) - $t ) * 50 );
}
printf( "Tamanho do índice serializado: %.0f KB · memória de pico %.0f MB\n", strlen( serialize( $rows ) ) / 1024, memory_get_peak_usage() / 1048576 );
$memo->setValue( null, array() );
