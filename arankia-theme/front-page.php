<?php
/**
 * صفحه اصلی (لندینگ) فروشگاه آرانکیا.
 *
 * وردپرس این فایل را همیشه برای صفحه اصلی سایت استفاده می‌کند (چه تنظیم
 * «نمایش صفحه اصلی» روی آخرین نوشته‌ها باشد چه روی یک صفحه ثابت). تمام
 * بخش‌های پویا (دسته‌بندی‌ها، پیشنهاد ویژه، پرفروش‌ها، تازه‌ها، مقالات)
 * از داده واقعی ووکامرس/وردپرس خوانده می‌شوند و در نبود محتوا به‌شکل
 * مناسب پنهان می‌شوند تا فروشگاه تازه‌راه‌اندازی‌شده هم خالی به‌نظر نرسد.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$has_wc = class_exists( 'WooCommerce' );

if ( $has_wc ) {
	$home_categories   = arankia_home_categories( 6 );
	$deal_products     = arankia_home_deal_products( 4 );
	$bestsellers       = arankia_home_bestsellers( 8 );
	$new_arrivals      = arankia_home_new_arrivals( 8 );
	$hero_products     = arankia_home_hero_products( 4 );
	$shop_url          = wc_get_page_permalink( 'shop' );
} else {
	$home_categories = $deal_products = $bestsellers = $new_arrivals = $hero_products = array(); // phpcs:ignore Generic.Formatting.MultipleStatementAlignment, Squiz.PHP.DisallowMultipleAssignments
	$shop_url        = home_url( '/' );
}

$recent_posts   = get_posts(
	array(
		'post_type'      => 'post',
		'posts_per_page' => 3,
		'ignore_sticky_posts' => 1,
	)
);
$blog_index_url = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' );
?>

<!-- ================= HERO ================= -->
<section class="home-hero">
	<div class="container home-hero-grid">
		<div class="home-hero-copy">
			<span class="home-eyebrow"><?php esc_html_e( 'معرفیِ نسخه‌ی تازه‌ی آرانکیا', 'arankia' ); ?></span>
			<h1><?php echo esc_html( get_theme_mod( 'arankia_hero_title', arankia_home_default( 'hero_title' ) ) ); ?></h1>
			<p class="home-hero-lede"><?php echo esc_html( get_theme_mod( 'arankia_hero_subtitle', arankia_home_default( 'hero_subtitle' ) ) ); ?></p>
			<div class="home-hero-ctas">
				<a href="<?php echo esc_url( $shop_url ); ?>" class="btn btn-primary"><?php esc_html_e( 'مشاهده فروشگاه', 'arankia' ); ?></a>
				<a href="#home-categories" class="btn btn-ghost"><?php esc_html_e( 'دسته‌بندی‌ها را ببینید', 'arankia' ); ?></a>
			</div>
			<ul class="home-hero-trust">
				<li><?php arankia_icon( 'truck' ); ?><?php esc_html_e( 'ارسال به سراسر ایران', 'arankia' ); ?></li>
				<li><?php arankia_icon( 'shield' ); ?><?php esc_html_e( 'پرداخت ۱۰۰٪ امن', 'arankia' ); ?></li>
				<li><?php arankia_icon( 'badge' ); ?><?php esc_html_e( 'ضمانت اصالت کالا', 'arankia' ); ?></li>
			</ul>
		</div>

		<div class="home-hero-visual" aria-hidden="true">
			<div class="home-blob"></div>
			<?php
			$tile_positions = array( 't1', 't2', 't3', 't4' );
			$tile_index     = 0;

			if ( $hero_products ) :
				foreach ( $hero_products as $product ) :
					if ( ! isset( $tile_positions[ $tile_index ] ) ) {
						break;
					}
					$visual = arankia_visual_placeholder( $product->get_id() );
					?>
					<div class="home-tile <?php echo esc_attr( $tile_positions[ $tile_index ] ); ?>">
						<span class="home-tile-icon home-tile-tint-<?php echo esc_attr( $visual['tint'] ); ?>"><?php arankia_icon( $visual['icon'] ); ?></span>
						<span class="home-tile-name"><?php echo esc_html( $product->get_name() ); ?></span>
						<span class="home-tile-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
					</div>
					<?php
					$tile_index++;
				endforeach;
			else :
				$demo_tiles = array(
					array( 'spray', 'primary', __( 'سمپاش پشتی ۱۶ لیتری', 'arankia' ), '۱,۲۹۰,۰۰۰ ' . __( 'تومان', 'arankia' ) ),
					array( 'watering-can', 'gold', __( 'کود مایع رشد ۱ لیتری', 'arankia' ), '۲۴۵,۰۰۰ ' . __( 'تومان', 'arankia' ) ),
					array( 'tractor', 'danger', __( 'بذرپاش دستی', 'arankia' ), '۸۹۰,۰۰۰ ' . __( 'تومان', 'arankia' ) ),
					array( 'leaf', 'primary', __( 'قیچی باغبانی حرفه‌ای', 'arankia' ), '۳۶۰,۰۰۰ ' . __( 'تومان', 'arankia' ) ),
				);
				foreach ( $demo_tiles as $i => $tile ) :
					?>
					<div class="home-tile <?php echo esc_attr( $tile_positions[ $i ] ); ?>">
						<span class="home-tile-icon home-tile-tint-<?php echo esc_attr( $tile[1] ); ?>"><?php arankia_icon( $tile[0] ); ?></span>
						<span class="home-tile-name"><?php echo esc_html( $tile[2] ); ?></span>
						<span class="home-tile-price"><?php echo esc_html( $tile[3] ); ?></span>
					</div>
					<?php
				endforeach;
			endif;
			?>
			<div class="home-tile rating">
				<span class="stars"><?php for ( $i = 0; $i < 5; $i++ ) { arankia_icon( 'star' ); } ?></span>
				<b>۴.۸</b>
			</div>
		</div>
	</div>
</section>

<!-- ================= TRUST STRIP ================= -->
<div class="home-trust-strip">
	<div class="container">
		<ul>
			<li><?php arankia_icon( 'truck' ); ?><span><strong><?php esc_html_e( 'ارسال سریع', 'arankia' ); ?></strong><span><?php esc_html_e( 'معمولاً ۲۴ تا ۴۸ ساعته', 'arankia' ); ?></span></span></li>
			<li><?php arankia_icon( 'wallet' ); ?><span><strong><?php esc_html_e( 'کیف پول اختصاصی', 'arankia' ); ?></strong><span><?php esc_html_e( 'شارژ کنید، سریع‌تر بخرید', 'arankia' ); ?></span></span></li>
			<li><?php arankia_icon( 'receipt' ); ?><span><strong><?php esc_html_e( 'فاکتور رسمی', 'arankia' ); ?></strong><span><?php esc_html_e( 'برای هر سفارش، همیشه', 'arankia' ); ?></span></span></li>
			<li><?php arankia_icon( 'headset' ); ?><span><strong><?php esc_html_e( 'پشتیبانی واقعی', 'arankia' ); ?></strong><span><?php esc_html_e( 'پاسخ‌گو، نه ربات', 'arankia' ); ?></span></span></li>
		</ul>
	</div>
</div>

<?php if ( $home_categories ) : ?>
<!-- ================= CATEGORIES ================= -->
<section class="home-section" id="home-categories">
	<div class="container">
		<div class="home-section-head">
			<div>
				<span class="home-eyebrow"><?php esc_html_e( 'از کجا شروع کنیم؟', 'arankia' ); ?></span>
				<h2 class="section-title" style="margin-top:8px"><?php esc_html_e( 'دسته‌بندی‌های فروشگاه', 'arankia' ); ?></h2>
			</div>
			<a href="<?php echo esc_url( $shop_url ); ?>" class="home-more-link"><?php esc_html_e( 'مشاهده همه دسته‌ها', 'arankia' ); ?> &laquo;</a>
		</div>
		<div class="home-shelf-scroller">
			<div class="home-shelf-track">
				<?php foreach ( $home_categories as $term ) : ?>
					<?php
					$visual       = arankia_home_category_visual( $term );
					$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
					?>
					<a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="home-cat-card">
						<span class="home-cat-icon home-tile-tint-<?php echo esc_attr( $visual['tint'] ); ?>">
							<?php if ( $thumbnail_id ) : ?>
								<?php echo wp_get_attachment_image( $thumbnail_id, 'thumbnail' ); ?>
							<?php else : ?>
								<?php arankia_icon( $visual['icon'] ); ?>
							<?php endif; ?>
						</span>
						<h3><?php echo esc_html( $term->name ); ?></h3>
						<?php /* translators: %d: number of products in the category. */ ?>
						<span><?php echo esc_html( sprintf( _n( '%d کالا', '%d کالا', $term->count, 'arankia' ), $term->count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $deal_products ) : ?>
<!-- ================= DEAL OF THE DAY ================= -->
<section class="home-section" style="padding-top:0">
	<div class="container">
		<div class="home-deal">
			<div class="home-deal-grid">
				<div class="home-deal-copy">
					<span class="home-eyebrow"><?php esc_html_e( 'پیشنهاد امروز', 'arankia' ); ?></span>
					<h2><?php esc_html_e( 'تخفیف‌های ویژه، تا پایان محدود', 'arankia' ); ?></h2>
					<p><?php esc_html_e( 'موجودی محدود است و با اتمام تخفیف، قیمت‌ها به حالت عادی برمی‌گردد.', 'arankia' ); ?></p>
					<div class="home-countdown" data-countdown="<?php echo esc_attr( arankia_home_deal_countdown_target( $deal_products ) ); ?>">
						<div class="cd-box"><b data-cd-h>۰۰</b><span><?php esc_html_e( 'ساعت', 'arankia' ); ?></span></div>
						<div class="cd-box"><b data-cd-m>۰۰</b><span><?php esc_html_e( 'دقیقه', 'arankia' ); ?></span></div>
						<div class="cd-box"><b data-cd-s>۰۰</b><span><?php esc_html_e( 'ثانیه', 'arankia' ); ?></span></div>
					</div>
					<a href="<?php echo esc_url( add_query_arg( 'on_sale', '1', $shop_url ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'مشاهده همه تخفیف‌ها', 'arankia' ); ?></a>
				</div>
				<div class="home-deal-products">
					<?php foreach ( $deal_products as $product ) : ?>
						<?php arankia_home_render_product_card( $product, 'sale' ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $bestsellers ) : ?>
<!-- ================= BESTSELLERS ================= -->
<section class="home-section">
	<div class="container">
		<div class="home-section-head">
			<div>
				<span class="home-eyebrow"><?php esc_html_e( 'محبوب‌ترین‌ها', 'arankia' ); ?></span>
				<h2 class="section-title" style="margin-top:8px"><?php esc_html_e( 'پرفروش‌های فروشگاه', 'arankia' ); ?></h2>
			</div>
			<div class="home-shelf-nav">
				<button type="button" aria-label="<?php esc_attr_e( 'بعدی', 'arankia' ); ?>" data-shelf-next="bestsellers"><?php arankia_icon( 'chevron' ); ?></button>
				<button type="button" aria-label="<?php esc_attr_e( 'قبلی', 'arankia' ); ?>" data-shelf-prev="bestsellers"><?php arankia_icon( 'chevron-right' ); ?></button>
			</div>
		</div>
		<div class="home-shelf-scroller">
			<div class="home-shelf-track" data-shelf="bestsellers">
				<?php foreach ( $bestsellers as $product ) : ?>
					<?php arankia_home_render_product_card( $product ); ?>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- ================= WHY ARANKIA ================= -->
<section class="home-section" style="background:var(--home-surface-2)">
	<div class="container">
		<div class="home-section-head">
			<div>
				<span class="home-eyebrow"><?php esc_html_e( 'تفاوت آرانکیا', 'arankia' ); ?></span>
				<h2 class="section-title" style="margin-top:8px"><?php esc_html_e( 'چرا از آرانکیا بخرید؟', 'arankia' ); ?></h2>
			</div>
		</div>
		<div class="home-feature-grid">
			<div class="home-feature-card">
				<span class="f-icon"><?php arankia_icon( 'wallet' ); ?></span>
				<h3><?php esc_html_e( 'کیف‌پول اختصاصی', 'arankia' ); ?></h3>
				<p><?php esc_html_e( 'یک‌بار شارژ کنید و در خریدهای بعدی، سریع‌تر و بدون دردسر پرداخت کنید.', 'arankia' ); ?></p>
			</div>
			<div class="home-feature-card">
				<span class="f-icon"><?php arankia_icon( 'headset' ); ?></span>
				<h3><?php esc_html_e( 'پشتیبانی واقعی', 'arankia' ); ?></h3>
				<p><?php esc_html_e( 'تیکت پشتیبانی مستقیم به تیم آرانکیا می‌رسد؛ معمولاً کمتر از ۲۴ ساعت پاسخ می‌گیرید.', 'arankia' ); ?></p>
			</div>
			<div class="home-feature-card">
				<span class="f-icon"><?php arankia_icon( 'receipt' ); ?></span>
				<h3><?php esc_html_e( 'فاکتور رسمی و پیگیری', 'arankia' ); ?></h3>
				<p><?php esc_html_e( 'برای هر سفارش فاکتور چاپی دریافت کنید و وضعیت آن را لحظه‌به‌لحظه دنبال کنید.', 'arankia' ); ?></p>
			</div>
			<div class="home-feature-card">
				<span class="f-icon"><?php arankia_icon( 'shield' ); ?></span>
				<h3><?php esc_html_e( 'ضمانت بازگشت وجه', 'arankia' ); ?></h3>
				<p><?php esc_html_e( 'تا ۷ روز پس از دریافت کالا، در صورت هر مشکلی امکان مرجوعی و بازگشت وجه دارید.', 'arankia' ); ?></p>
			</div>
		</div>
	</div>
</section>

<!-- ================= HOW IT WORKS ================= -->
<section class="home-section">
	<div class="container">
		<div class="home-section-head" style="justify-content:center;text-align:center">
			<div style="margin:0 auto">
				<span class="home-eyebrow" style="justify-content:center"><?php esc_html_e( 'مسیر خرید', 'arankia' ); ?></span>
				<h2 class="section-title" style="margin-top:8px"><?php esc_html_e( 'در سه قدم ساده', 'arankia' ); ?></h2>
			</div>
		</div>
		<div class="home-steps">
			<div class="home-step"><span class="step-num">۱</span><h3><?php esc_html_e( 'محصول را انتخاب کنید', 'arankia' ); ?></h3><p><?php esc_html_e( 'از میان کالاهای متنوع، آنچه را می‌خواهید پیدا کنید.', 'arankia' ); ?></p></div>
			<div class="home-step"><span class="step-num">۲</span><h3><?php esc_html_e( 'پرداخت امن', 'arankia' ); ?></h3><p><?php esc_html_e( 'با کیف‌پول یا درگاه بانکی، در چند ثانیه پرداخت را کامل کنید.', 'arankia' ); ?></p></div>
			<div class="home-step"><span class="step-num">۳</span><h3><?php esc_html_e( 'تحویل درِ منزل', 'arankia' ); ?></h3><p><?php esc_html_e( 'سفارش را پیگیری کنید تا سریع و سالم دستتان برسد.', 'arankia' ); ?></p></div>
		</div>
	</div>
</section>

<?php if ( $new_arrivals ) : ?>
<!-- ================= NEW ARRIVALS ================= -->
<section class="home-section" style="background:var(--home-surface-2)">
	<div class="container">
		<div class="home-section-head">
			<div>
				<span class="home-eyebrow"><?php esc_html_e( 'تازه از راه رسیده', 'arankia' ); ?></span>
				<h2 class="section-title" style="margin-top:8px"><?php esc_html_e( 'جدیدترین‌های فروشگاه', 'arankia' ); ?></h2>
			</div>
			<a href="<?php echo esc_url( add_query_arg( 'orderby', 'date', $shop_url ) ); ?>" class="home-more-link"><?php esc_html_e( 'مشاهده همه', 'arankia' ); ?> &laquo;</a>
		</div>
		<div class="home-shelf-scroller">
			<div class="home-shelf-track">
				<?php foreach ( $new_arrivals as $product ) : ?>
					<?php arankia_home_render_product_card( $product, 'new' ); ?>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- ================= TESTIMONIALS ================= -->
<section class="home-section">
	<div class="container">
		<div class="home-section-head">
			<div>
				<span class="home-eyebrow"><?php esc_html_e( 'تجربه مشتری‌ها', 'arankia' ); ?></span>
				<h2 class="section-title" style="margin-top:8px"><?php esc_html_e( 'حرف مشتری‌های آرانکیا', 'arankia' ); ?></h2>
			</div>
		</div>
		<div class="home-testi-grid">
			<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
				<?php
				$text = get_theme_mod( 'arankia_testimonial_text_' . $i, arankia_home_default( 'testimonial_text_' . $i ) );
				$name = get_theme_mod( 'arankia_testimonial_name_' . $i, arankia_home_default( 'testimonial_name_' . $i ) );
				$city = get_theme_mod( 'arankia_testimonial_city_' . $i, arankia_home_default( 'testimonial_city_' . $i ) );
				if ( ! $text ) {
					continue;
				}
				$initials = mb_substr( $name, 0, 1 );
				?>
				<div class="home-testi-card">
					<span class="stars"><?php for ( $s = 0; $s < 5; $s++ ) { arankia_icon( 'star' ); } ?></span>
					<p>«<?php echo esc_html( $text ); ?>»</p>
					<div class="home-testi-who">
						<span class="home-testi-avatar"><?php echo esc_html( $initials ); ?></span>
						<div><strong><?php echo esc_html( $name ); ?></strong><span><?php echo esc_html( $city ); ?></span></div>
					</div>
				</div>
			<?php endfor; ?>
		</div>
		<p class="home-note"><?php esc_html_e( 'این نظرات نمونه‌اند — از تنظیمات قالب (سفارشی‌سازی ← صفحه اصلی) با نظرات واقعی مشتریان جایگزین کنید.', 'arankia' ); ?></p>
	</div>
</section>

<!-- ================= STATS ================= -->
<section class="home-section" style="padding-top:0">
	<div class="container">
		<div class="home-stats">
			<ul>
				<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
					<li>
						<b><?php echo esc_html( get_theme_mod( 'arankia_stat_value_' . $i, arankia_home_default( 'stat_value_' . $i ) ) ); ?></b>
						<span><?php echo esc_html( get_theme_mod( 'arankia_stat_label_' . $i, arankia_home_default( 'stat_label_' . $i ) ) ); ?></span>
					</li>
				<?php endfor; ?>
			</ul>
		</div>
	</div>
</section>

<?php if ( $recent_posts ) : ?>
<!-- ================= BLOG ================= -->
<section class="home-section" style="background:var(--home-surface-2)">
	<div class="container">
		<div class="home-section-head">
			<div>
				<span class="home-eyebrow"><?php esc_html_e( 'مجله آرانکیا', 'arankia' ); ?></span>
				<h2 class="section-title" style="margin-top:8px"><?php esc_html_e( 'جدیدترین مطالب', 'arankia' ); ?></h2>
			</div>
			<a href="<?php echo esc_url( $blog_index_url ); ?>" class="home-more-link"><?php esc_html_e( 'همه مطالب', 'arankia' ); ?> &laquo;</a>
		</div>
		<div class="home-blog-grid">
			<?php foreach ( $recent_posts as $post ) : setup_postdata( $post ); ?>
				<a href="<?php the_permalink(); ?>" class="home-blog-card">
					<div class="home-blog-thumb">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'arankia-blog-thumb' ); ?>
						<?php else : ?>
							<?php arankia_icon( 'box' ); ?>
						<?php endif; ?>
					</div>
					<div class="home-blog-body">
						<?php $cats = get_the_category(); ?>
						<?php if ( $cats ) : ?><span class="home-blog-cat"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
						<h3 class="home-blog-title"><?php the_title(); ?></h3>
						<div class="home-blog-meta"><span><?php echo esc_html( arankia_reading_time() ); ?></span><span><?php echo esc_html( get_the_date() ); ?></span></div>
					</div>
				</a>
			<?php endforeach; wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- ================= CLUB / NEWSLETTER ================= -->
<section class="home-section">
	<div class="container">
		<div class="home-club">
			<div class="home-club-copy">
				<h2><?php echo esc_html( get_theme_mod( 'arankia_newsletter_title', arankia_home_default( 'newsletter_title' ) ) ); ?></h2>
				<p><?php echo esc_html( get_theme_mod( 'arankia_newsletter_text', arankia_home_default( 'newsletter_text' ) ) ); ?></p>
			</div>
			<div>
				<form class="home-club-form" data-club-form>
					<input type="email" placeholder="<?php esc_attr_e( 'ایمیل شما', 'arankia' ); ?>" required />
					<button type="submit"><?php esc_html_e( 'عضویت رایگان', 'arankia' ); ?></button>
				</form>
				<p class="home-club-status" data-club-status></p>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
