<?php 
// Функция позволяющая втоматически заполнять вариативность товара в зависимости от принадлежности к колекции
add_action( 'acf/save_post', 'save_poduct_with_variable_image', 99 );
function save_poduct_with_variable_image($post_id ){

	global $post;
	global $wpdb;

	if(get_post_type( $post_id ) == 'product'):
    //находим id нужной таксаномии
	$tax = $wpdb->get_var($wpdb->prepare(
		"SELECT wp_term_taxonomy.term_id, wp_term_relationships.term_taxonomy_id
		FROM  wp_term_relationships, wp_term_taxonomy
		WHERE  wp_term_taxonomy.taxonomy = 'collection' AND
		wp_term_relationships.term_taxonomy_id = wp_term_taxonomy.term_id AND
		wp_term_relationships.object_id = $post->ID
		"
	));
    //находим текущий продукт
	$querys = new WP_Query( [
		'post_type'    => 'product',
		'p' => $post->ID
	] );

	$term = get_term( $tax, 'collection' );

	while ( $querys->have_posts() ) {
		$querys->the_post();
		$product = wc_get_product( $post->ID);
        //ACF цикл 
		if(have_rows('colors', $term)):
			while(have_rows('colors', $term)): the_row();

				$c_name = get_sub_field('name'); //берем название цвета
				$trans = rus2translit($c_name);
				$title = $product -> get_title().' - '.$trans;

                // находим ID конкретной колекции
				$my_term = $wpdb->query("SELECT term_id FROM $wpdb->terms 
					WHERE (slug = '$trans' )"
				);
                // Если нет колекции, создем ее 
				if(!$my_term){
					$insert_data = wp_insert_term(
						$c_name,  
						'pa_color', 
						array(
							'description' => 'Цвет.',
							'slug' => $trans
						)
					);
				}

                //создаем связь между таваром и колекцией 
				$wpdb->query( "INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id, term_order ) VALUES ( '$post->ID', '$my_term ', '0')");

                //Ищем вариотивный товар, который дополнительно создаеться 
				$pst = $wpdb->query("SELECT ID FROM $wpdb->posts 
					WHERE (post_title = '$title' AND post_parent = '$post->ID')"
				);

                //если не находим создаем его
				if(!$pst){
					$color = get_sub_field('color');
					$image = get_sub_field('image');
					if($image){ $url =  wp_get_attachment_url($image['id'], 'full'); } else { $url= wp_get_attachment_url($color['id'], 'thumbnail'); };
					$post_id = wp_insert_post(  wp_slash( array(
						'post_title'	=> $c_name,
						'post_excerpt'  => 'ra_colot:color',
						'post_status'   => 'publish',
						'post_type'     => 'product_variation',
						'post_author'   => $user_ID,
						'ping_status'   => get_option('default_ping_status'),
						'post_parent'   => $post->ID,
						'menu_order'    => 0,
						'to_ping'       => '',
						'pinged'        => '',
						'post_password' => '',
						'meta_input'    => [ 'meta_key'=>'meta_value' ],
					) ) );

                    //Всю дополнительную информацию добавляем в meta
					add_post_meta( $post_id, '_regular_price', $product->get_price());
					add_post_meta( $post_id, '_price', $product->get_price());
					add_post_meta( $post_id, '_sku', $product->get_sku());
					add_post_meta( $post_id, 'attribute_pa_%d1%86%d0%b2%d0%b5%d1%82', $trans);
					add_post_meta( $post_id, '_thumbnail_id', $color['id']);
					add_post_meta( $post_id, '_product_version', '4.0.1');
					add_post_meta( $post_id, '_tax_status', 'taxable');
					add_post_meta( $post_id, '_tax_class', 'parent');
					add_post_meta( $post_id, '_manage_stock', 'no');
					add_post_meta( $post_id, '_backorders', 'no');
					add_post_meta( $post_id, '_sold_individually', 'no');
					add_post_meta( $post_id, '_virtual', 'no');
					add_post_meta( $post_id, '_downloadable', 'no');

                    // Добавляем данные в таблицу wp_wc_product_meta_lookup
                    $wpdb->query( "INSERT INTO $wpdb->wc_product_meta_lookup (product_id, sku, min_price, max_price, stock_status ) VALUES ( '$post_id', '$product->get_sku()', '$product->get_price()', '$product->get_price()', 'instock')");
					
				}
                
                //Ищем цвет в атребутах товара
                $attributes = $wpdb->get_var("SELECT meta_value FROM $wpdb->postmeta 
                WHERE (post_id = '$post->ID' AND meta_key = '_product_attributes')"
                );

                if($attributes){
                    //добавляем к атрибуту нужный цвет
                    $attributes = maybe_unserialize( $attributes );
                    $attr = array();

                    if(!$attributes['pa_%d1%86%d0%b2%d0%b5%d1%82']){
                        $attr=[
                            'name'  => 'pa_color',
                            'value' => '',
                            'position' => 0,
                            'is_visible' => 1,
                            'is_variation' => 1,
                            'is_taxonomy' => 1
                        ];
                        
                        $attributes['pa_%d1%86%d0%b2%d0%b5%d1%82'] = $attr;
                        update_post_meta($post->ID, '_product_attributes', wp_slash( $attributes));

                        $wpdb->query( "INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id, term_order ) VALUES ( '$post->ID', '$my_term ', '0')");
                    }

                }else{
                    //добавляем атебут цвет 
                    $attr = 'a:1:{s:27:"pa_%d1%86%d0%b2%d0%b5%d1%82";a:6:{s:4:"name";s:11:"pa_color";s:5:"value";s:0:"";s:8:"position";s:1:"0";s:10:"is_visible";s:1:"1";s:12:"is_variation";s:1:"1";s:11:"is_taxonomy";s:1:"1";}}';
                    
                    $arr = maybe_unserialize($attr);
                    update_post_meta($post->ID, '_product_attributes', $arr );
                    wp_set_object_terms($post->ID, $my_term, 'pa_color');

                }

			endwhile;
		endif;
	
	}; 

	wp_reset_postdata();

    endif;
}
// Транслитерация строк.
function rus2translit($string) {
    $converter = array(
        'а' => 'a',   'б' => 'b',   'в' => 'v',
        'г' => 'g',   'д' => 'd',   'е' => 'e',
        'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
        'и' => 'i',   'й' => 'y',   'к' => 'k',
        'л' => 'l',   'м' => 'm',   'н' => 'n',
        'о' => 'o',   'п' => 'p',   'р' => 'r',
        'с' => 's',   'т' => 't',   'у' => 'u',
        'ф' => 'f',   'х' => 'h',   'ц' => 'c',
        'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
        'ь' => '',  'ы' => 'y',   'ъ' => '',
		'э' => 'e',   'ю' => 'yu',  'я' => 'ya',
		' ' => '',
        
        'А' => 'A',   'Б' => 'B',   'В' => 'V',
        'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
        'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
        'И' => 'I',   'Й' => 'Y',   'К' => 'K',
        'Л' => 'L',   'М' => 'M',   'Н' => 'N',
        'О' => 'O',   'П' => 'P',   'Р' => 'R',
        'С' => 'S',   'Т' => 'T',   'У' => 'U',
        'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
        'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
        'Ь' => '',  'Ы' => 'Y',   'Ъ' => '',
        'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
    );
    return strtr($string, $converter);
}
function str2url($str) {
    // переводим в транслит
    $str = rus2translit($str);
    // в нижний регистр
    $str = strtolower($str);
    // заменям все ненужное нам на "-"
    $str = preg_replace('~[^-a-z0-9_]+~u', '-', $str);
    // удаляем начальные и конечные '-'
    $str = trim($str, "-");
    return $str;
}