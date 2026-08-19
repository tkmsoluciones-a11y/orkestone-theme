<div class="wrap orke-hub-wrap">
	<div class="orke-hub-header">
		<h1><?php echo esc_html__( 'New Client Configuration', 'orkestone-agency-hub' ); ?></h1>
		<p class="description"><?php echo esc_html__( 'Complete the briefing form below to generate a site configuration, calculate the budget, and create a payment-ready draft.', 'orkestone-agency-hub' ); ?></p>
	</div>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="orke-hub-notice orke-hub-notice--error">
			<strong><?php esc_html_e( 'Please fix the following errors:', 'orkestone-agency-hub' ); ?></strong>
			<ul style="margin:8px 0 0 16px;list-style:disc;">
				<?php foreach ( $errors as $field => $message ) : ?>
					<li><?php echo esc_html( $message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $budget_result ) ) : ?>
		<div class="orke-hub-notice orke-hub-notice--success">
			<?php esc_html_e( 'Budget calculated successfully! Review the breakdown below.', 'orkestone-agency-hub' ); ?>
		</div>
	<?php endif; ?>

	<div class="orke-hub-tabs">
		<ul class="orke-hub-tabs-nav">
			<?php foreach ( $tab_labels as $key => $label ) : ?>
				<li class="<?php echo $active_tab === $key ? 'active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
					<a href="#tab-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>

		<form id="orke-briefing-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="action" value="orke_save_briefing" />

			<!-- Tab 1: Branding -->
			<div id="tab-branding" class="orke-hub-tab-panel <?php echo $active_tab === 'branding' ? 'active' : ''; ?>">
				<div class="orke-hub-card">
					<h2><?php esc_html_e( 'Branding', 'orkestone-agency-hub' ); ?></h2>

					<div class="orke-hub-field">
						<label for="orke_site_name"><?php esc_html_e( 'Site Name *', 'orkestone-agency-hub' ); ?></label>
						<input type="text" id="orke_site_name" name="orke_site_name"
							value="<?php echo isset( $saved_data['branding']['site_name'] ) ? esc_attr( $saved_data['branding']['site_name'] ) : ''; ?>"
							placeholder="<?php esc_attr_e( 'e.g., Acme Corp', 'orkestone-agency-hub' ); ?>"
							required />
						<?php if ( isset( $errors['site_name'] ) ) : ?>
							<p class="error"><?php echo esc_html( $errors['site_name'] ); ?></p>
						<?php endif; ?>
					</div>

					<div class="orke-hub-field">
						<label for="orke_tagline"><?php esc_html_e( 'Tagline', 'orkestone-agency-hub' ); ?></label>
						<input type="text" id="orke_tagline" name="orke_tagline"
							value="<?php echo isset( $saved_data['branding']['tagline'] ) ? esc_attr( $saved_data['branding']['tagline'] ) : ''; ?>"
							placeholder="<?php esc_attr_e( 'e.g., Building the future', 'orkestone-agency-hub' ); ?>" />
					</div>

					<div class="orke-hub-field">
						<label for="orke_logo_id"><?php esc_html_e( 'Logo', 'orkestone-agency-hub' ); ?></label>
						<input type="hidden" id="orke_logo_id" name="orke_logo_id"
							value="<?php echo isset( $saved_data['branding']['logo_id'] ) ? esc_attr( $saved_data['branding']['logo_id'] ) : '0'; ?>" />
						<input type="url" id="orke_logo_url" name="orke_logo_url"
							value="<?php echo isset( $saved_data['branding']['logo_url'] ) ? esc_attr( $saved_data['branding']['logo_url'] ) : ''; ?>"
							placeholder="<?php esc_attr_e( 'Or paste image URL directly', 'orkestone-agency-hub' ); ?>" />
						<p class="description"><?php esc_html_e( 'Upload an asset in the Asset Library first, then enter the URL above.', 'orkestone-agency-hub' ); ?></p>
					</div>

					<div class="orke-hub-field">
						<label for="orke_primary_color"><?php esc_html_e( 'Primary Color', 'orkestone-agency-hub' ); ?></label>
						<input type="color" id="orke_primary_color" name="orke_primary_color"
							value="<?php echo isset( $saved_data['branding']['primary_color'] ) ? esc_attr( $saved_data['branding']['primary_color'] ) : '#1a365d'; ?>" />
					</div>

					<div class="orke-hub-field">
						<label for="orke_secondary_color"><?php esc_html_e( 'Secondary Color', 'orkestone-agency-hub' ); ?></label>
						<input type="color" id="orke_secondary_color" name="orke_secondary_color"
							value="<?php echo isset( $saved_data['branding']['secondary_color'] ) ? esc_attr( $saved_data['branding']['secondary_color'] ) : '#e2e8f0'; ?>" />
					</div>

					<div class="orke-hub-field">
						<label for="orke_accent_color"><?php esc_html_e( 'Accent Color', 'orkestone-agency-hub' ); ?></label>
						<input type="color" id="orke_accent_color" name="orke_accent_color"
							value="<?php echo isset( $saved_data['branding']['accent_color'] ) ? esc_attr( $saved_data['branding']['accent_color'] ) : '#3b82f6'; ?>" />
					</div>

					<h3><?php esc_html_e( 'Typography', 'orkestone-agency-hub' ); ?></h3>

					<div class="orke-hub-field">
						<label for="orke_heading_font"><?php esc_html_e( 'Heading Font', 'orkestone-agency-hub' ); ?></label>
						<select id="orke_heading_font" name="orke_heading_font">
							<option value="Inter" <?php selected( $saved_data['branding']['heading_font'] ?? '', 'Inter' ); ?>><?php esc_html_e( 'Inter', 'orkestone-agency-hub' ); ?></option>
							<option value="System UI" <?php selected( $saved_data['branding']['heading_font'] ?? '', 'System UI' ); ?>><?php esc_html_e( 'System UI', 'orkestone-agency-hub' ); ?></option>
							<option value="Georgia" <?php selected( $saved_data['branding']['heading_font'] ?? '', 'Georgia' ); ?>><?php esc_html_e( 'Georgia', 'orkestone-agency-hub' ); ?></option>
							<option value="Roboto" <?php selected( $saved_data['branding']['heading_font'] ?? '', 'Roboto' ); ?>><?php esc_html_e( 'Roboto', 'orkestone-agency-hub' ); ?></option>
						</select>
					</div>

					<div class="orke-hub-field">
						<label for="orke_body_font"><?php esc_html_e( 'Body Font', 'orkestone-agency-hub' ); ?></label>
						<select id="orke_body_font" name="orke_body_font">
							<option value="Inter" <?php selected( $saved_data['branding']['body_font'] ?? '', 'Inter' ); ?>><?php esc_html_e( 'Inter', 'orkestone-agency-hub' ); ?></option>
							<option value="System UI" <?php selected( $saved_data['branding']['body_font'] ?? '', 'System UI' ); ?>><?php esc_html_e( 'System UI', 'orkestone-agency-hub' ); ?></option>
							<option value="Georgia" <?php selected( $saved_data['branding']['body_font'] ?? '', 'Georgia' ); ?>><?php esc_html_e( 'Georgia', 'orkestone-agency-hub' ); ?></option>
							<option value="Roboto" <?php selected( $saved_data['branding']['body_font'] ?? '', 'Roboto' ); ?>><?php esc_html_e( 'Roboto', 'orkestone-agency-hub' ); ?></option>
						</select>
					</div>
				</div>
			</div>

			<!-- Tab 2: Pages & Sections -->
			<div id="tab-pages" class="orke-hub-tab-panel <?php echo $active_tab === 'pages' ? 'active' : ''; ?>">
				<div class="orke-hub-card">
					<h2><?php esc_html_e( 'Pages & Sections', 'orkestone-agency-hub' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Add the pages for this site. Each page can have multiple sections that define its layout.', 'orkestone-agency-hub' ); ?></p>

					<?php if ( isset( $errors['pages'] ) ) : ?>
						<p class="error" style="margin-bottom:12px;"><?php echo esc_html( $errors['pages'] ); ?></p>
					<?php endif; ?>

					<div id="orke-pages-container">
						<?php
						$pages = isset( $saved_data['pages'] ) ? $saved_data['pages'] : array();
						if ( empty( $pages ) ) {
							$pages = array(
								array( 'title' => '', 'slug' => '', 'sections' => array() ),
							);
						}
						foreach ( $pages as $index => $page ) :
							?>
							<div class="orke-hub-page-row orke-hub-card" data-index="<?php echo esc_attr( $index ); ?>">
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Page Title', 'orkestone-agency-hub' ); ?></label>
									<input type="text" name="orke_pages[<?php echo esc_attr( $index ); ?>][title]"
										value="<?php echo isset( $page['title'] ) ? esc_attr( $page['title'] ) : ''; ?>"
										placeholder="<?php esc_attr_e( 'e.g., Home', 'orkestone-agency-hub' ); ?>" />
								</div>
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Slug', 'orkestone-agency-hub' ); ?></label>
									<input type="text" name="orke_pages[<?php echo esc_attr( $index ); ?>][slug]"
										value="<?php echo isset( $page['slug'] ) ? esc_attr( $page['slug'] ) : ''; ?>"
										placeholder="<?php esc_attr_e( 'e.g., home', 'orkestone-agency-hub' ); ?>" />
								</div>
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Sections', 'orkestone-agency-hub' ); ?></label>
									<select name="orke_pages[<?php echo esc_attr( $index ); ?>][sections][]" multiple class="orke-section-select" style="height:80px;">
										<option value="hero" <?php echo in_array( 'hero', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Hero', 'orkestone-agency-hub' ); ?></option>
										<option value="hero-centered" <?php echo in_array( 'hero-centered', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Hero (Centered)', 'orkestone-agency-hub' ); ?></option>
										<option value="services-grid" <?php echo in_array( 'services-grid', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Services Grid', 'orkestone-agency-hub' ); ?></option>
										<option value="about" <?php echo in_array( 'about', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'About', 'orkestone-agency-hub' ); ?></option>
										<option value="testimonials" <?php echo in_array( 'testimonials', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Testimonials', 'orkestone-agency-hub' ); ?></option>
										<option value="pricing" <?php echo in_array( 'pricing', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Pricing', 'orkestone-agency-hub' ); ?></option>
										<option value="faq-section" <?php echo in_array( 'faq-section', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'FAQ', 'orkestone-agency-hub' ); ?></option>
										<option value="cta" <?php echo in_array( 'cta', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Call to Action', 'orkestone-agency-hub' ); ?></option>
										<option value="contact-section" <?php echo in_array( 'contact-section', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Contact', 'orkestone-agency-hub' ); ?></option>
										<option value="features" <?php echo in_array( 'features', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Features', 'orkestone-agency-hub' ); ?></option>
										<option value="gallery" <?php echo in_array( 'gallery', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Gallery', 'orkestone-agency-hub' ); ?></option>
										<option value="team-grid" <?php echo in_array( 'team-grid', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Team Grid', 'orkestone-agency-hub' ); ?></option>
										<option value="content" <?php echo in_array( 'content', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Content', 'orkestone-agency-hub' ); ?></option>
										<option value="form" <?php echo in_array( 'form', ( isset( $page['sections'] ) ? $page['sections'] : array() ), true ) ? 'selected' : ''; ?>><?php esc_html_e( 'Form', 'orkestone-agency-hub' ); ?></option>
									</select>
								</div>
								<button type="button" class="orke-hub-button orke-hub-button--danger orke-remove-page"><?php esc_html_e( 'Remove Page', 'orkestone-agency-hub' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>

					<button type="button" class="orke-hub-button orke-hub-button--secondary" id="orke-add-page">
						<?php esc_html_e( '+ Add Page', 'orkestone-agency-hub' ); ?>
					</button>
				</div>
			</div>

			<!-- Tab 3: Content & Models -->
			<div id="tab-content" class="orke-hub-tab-panel <?php echo $active_tab === 'content' ? 'active' : ''; ?>">
				<div class="orke-hub-card">
					<h2><?php esc_html_e( 'Content & Models', 'orkestone-agency-hub' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Define the structured content for this site. Add services, team members, pricing plans, FAQ items, and testimonials.', 'orkestone-agency-hub' ); ?></p>

					<!-- Services -->
					<h3><?php esc_html_e( 'Services', 'orkestone-agency-hub' ); ?></h3>
					<div id="orke-services-container">
						<?php
						$services = isset( $saved_data['content_models']['services'] ) ? $saved_data['content_models']['services'] : array();
						foreach ( $services as $index => $service ) :
							?>
							<div class="orke-hub-model-row orke-hub-card" data-index="<?php echo esc_attr( $index ); ?>">
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Service Title', 'orkestone-agency-hub' ); ?></label>
									<input type="text" name="orke_services[<?php echo esc_attr( $index ); ?>][title]"
										value="<?php echo esc_attr( $service['title'] ); ?>" />
								</div>
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Description', 'orkestone-agency-hub' ); ?></label>
									<textarea name="orke_services[<?php echo esc_attr( $index ); ?>][description]" rows="2"><?php echo esc_textarea( isset( $service['description'] ) ? $service['description'] : '' ); ?></textarea>
								</div>
								<button type="button" class="orke-hub-button orke-hub-button--danger orke-remove-model"><?php esc_html_e( 'Remove', 'orkestone-agency-hub' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="orke-hub-button orke-hub-button--secondary orke-add-model" data-container="orke-services-container" data-name="orke_services">
						<?php esc_html_e( '+ Add Service', 'orkestone-agency-hub' ); ?>
					</button>

					<!-- Team Members -->
					<h3 style="margin-top:24px;"><?php esc_html_e( 'Team Members', 'orkestone-agency-hub' ); ?></h3>
					<div id="orke-team-container">
						<?php
						$team = isset( $saved_data['content_models']['team'] ) ? $saved_data['content_models']['team'] : array();
						foreach ( $team as $index => $member ) :
							?>
							<div class="orke-hub-model-row orke-hub-card" data-index="<?php echo esc_attr( $index ); ?>">
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Name', 'orkestone-agency-hub' ); ?></label>
									<input type="text" name="orke_team[<?php echo esc_attr( $index ); ?>][name]"
										value="<?php echo esc_attr( $member['name'] ); ?>" />
								</div>
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Role', 'orkestone-agency-hub' ); ?></label>
									<input type="text" name="orke_team[<?php echo esc_attr( $index ); ?>][role]"
										value="<?php echo isset( $member['role'] ) ? esc_attr( $member['role'] ) : ''; ?>" />
								</div>
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Bio', 'orkestone-agency-hub' ); ?></label>
									<textarea name="orke_team[<?php echo esc_attr( $index ); ?>][description]" rows="2"><?php echo esc_textarea( isset( $member['description'] ) ? $member['description'] : '' ); ?></textarea>
								</div>
								<button type="button" class="orke-hub-button orke-hub-button--danger orke-remove-model"><?php esc_html_e( 'Remove', 'orkestone-agency-hub' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="orke-hub-button orke-hub-button--secondary orke-add-model" data-container="orke-team-container" data-name="orke_team">
						<?php esc_html_e( '+ Add Team Member', 'orkestone-agency-hub' ); ?>
					</button>

					<!-- Pricing Plans -->
					<h3 style="margin-top:24px;"><?php esc_html_e( 'Pricing Plans', 'orkestone-agency-hub' ); ?></h3>
					<div id="orke-pricing-container">
						<?php
						$pricing_plans = isset( $saved_data['content_models']['pricing'] ) ? $saved_data['content_models']['pricing'] : array();
						foreach ( $pricing_plans as $index => $plan ) :
							?>
							<div class="orke-hub-model-row orke-hub-card" data-index="<?php echo esc_attr( $index ); ?>">
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Plan Name', 'orkestone-agency-hub' ); ?></label>
									<input type="text" name="orke_pricing[<?php echo esc_attr( $index ); ?>][plan]"
										value="<?php echo esc_attr( $plan['plan'] ); ?>" />
								</div>
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Price', 'orkestone-agency-hub' ); ?></label>
									<input type="number" name="orke_pricing[<?php echo esc_attr( $index ); ?>][price]" step="0.01"
										value="<?php echo isset( $plan['price'] ) ? esc_attr( $plan['price'] ) : '0'; ?>" />
								</div>
								<button type="button" class="orke-hub-button orke-hub-button--danger orke-remove-model"><?php esc_html_e( 'Remove', 'orkestone-agency-hub' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="orke-hub-button orke-hub-button--secondary orke-add-model" data-container="orke-pricing-container" data-name="orke_pricing">
						<?php esc_html_e( '+ Add Pricing Plan', 'orkestone-agency-hub' ); ?>
					</button>

					<!-- FAQ Items -->
					<h3 style="margin-top:24px;"><?php esc_html_e( 'FAQ Items', 'orkestone-agency-hub' ); ?></h3>
					<div id="orke-faq-container">
						<?php
						$faq_items = isset( $saved_data['content_models']['faq'] ) ? $saved_data['content_models']['faq'] : array();
						foreach ( $faq_items as $index => $item ) :
							?>
							<div class="orke-hub-model-row orke-hub-card" data-index="<?php echo esc_attr( $index ); ?>">
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Question', 'orkestone-agency-hub' ); ?></label>
									<input type="text" name="orke_faq[<?php echo esc_attr( $index ); ?>][question]"
										value="<?php echo esc_attr( $item['question'] ); ?>" />
								</div>
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Answer', 'orkestone-agency-hub' ); ?></label>
									<textarea name="orke_faq[<?php echo esc_attr( $index ); ?>][answer]" rows="2"><?php echo esc_textarea( isset( $item['answer'] ) ? $item['answer'] : '' ); ?></textarea>
								</div>
								<button type="button" class="orke-hub-button orke-hub-button--danger orke-remove-model"><?php esc_html_e( 'Remove', 'orkestone-agency-hub' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="orke-hub-button orke-hub-button--secondary orke-add-model" data-container="orke-faq-container" data-name="orke_faq">
						<?php esc_html_e( '+ Add FAQ Item', 'orkestone-agency-hub' ); ?>
					</button>

					<!-- Testimonials -->
					<h3 style="margin-top:24px;"><?php esc_html_e( 'Testimonials', 'orkestone-agency-hub' ); ?></h3>
					<div id="orke-testimonials-container">
						<?php
						$testimonials = isset( $saved_data['content_models']['testimonials'] ) ? $saved_data['content_models']['testimonials'] : array();
						foreach ( $testimonials as $index => $t ) :
							?>
							<div class="orke-hub-model-row orke-hub-card" data-index="<?php echo esc_attr( $index ); ?>">
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Quote', 'orkestone-agency-hub' ); ?></label>
									<textarea name="orke_testimonials[<?php echo esc_attr( $index ); ?>][quote]" rows="2"><?php echo esc_textarea( $t['quote'] ); ?></textarea>
								</div>
								<div class="orke-hub-field">
									<label><?php esc_html_e( 'Author', 'orkestone-agency-hub' ); ?></label>
									<input type="text" name="orke_testimonials[<?php echo esc_attr( $index ); ?>][author]"
										value="<?php echo isset( $t['author'] ) ? esc_attr( $t['author'] ) : ''; ?>" />
								</div>
								<button type="button" class="orke-hub-button orke-hub-button--danger orke-remove-model"><?php esc_html_e( 'Remove', 'orkestone-agency-hub' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="orke-hub-button orke-hub-button--secondary orke-add-model" data-container="orke-testimonials-container" data-name="orke_testimonials">
						<?php esc_html_e( '+ Add Testimonial', 'orkestone-agency-hub' ); ?>
					</button>
				</div>
			</div>

			<!-- Tab 4: Navigation & SEO -->
			<div id="tab-navigation" class="orke-hub-tab-panel <?php echo $active_tab === 'navigation' ? 'active' : ''; ?>">
				<div class="orke-hub-card">
					<h2><?php esc_html_e( 'Navigation & SEO', 'orkestone-agency-hub' ); ?></h2>

					<h3><?php esc_html_e( 'Menu Items', 'orkestone-agency-hub' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Define the main navigation menu. Each item needs a label and URL.', 'orkestone-agency-hub' ); ?></p>

					<?php if ( isset( $errors['navigation'] ) ) : ?>
						<p class="error" style="margin-bottom:12px;"><?php echo esc_html( $errors['navigation'] ); ?></p>
					<?php endif; ?>

					<div id="orke-menu-container">
						<?php
						$menu_items = isset( $saved_data['navigation'] ) ? $saved_data['navigation'] : array();
						if ( empty( $menu_items ) ) {
							$menu_items = array(
								array( 'label' => '', 'url' => '' ),
							);
						}
						foreach ( $menu_items as $index => $item ) :
							?>
							<div class="orke-hub-menu-row" data-index="<?php echo esc_attr( $index ); ?>">
								<div class="orke-hub-field" style="display:inline-block;width:calc(50% - 8px);margin-right:8px;">
									<input type="text" name="orke_menu_items[<?php echo esc_attr( $index ); ?>][label]"
										value="<?php echo isset( $item['label'] ) ? esc_attr( $item['label'] ) : ''; ?>"
										placeholder="<?php esc_attr_e( 'Label', 'orkestone-agency-hub' ); ?>" />
								</div>
								<div class="orke-hub-field" style="display:inline-block;width:calc(50% - 60px);">
									<input type="text" name="orke_menu_items[<?php echo esc_attr( $index ); ?>][url]"
										value="<?php echo isset( $item['url'] ) ? esc_attr( $item['url'] ) : ''; ?>"
										placeholder="<?php esc_attr_e( 'URL (e.g., /services)', 'orkestone-agency-hub' ); ?>" />
								</div>
								<button type="button" class="orke-hub-button orke-hub-button--danger orke-remove-menu-item" style="vertical-align:top;">&times;</button>
							</div>
						<?php endforeach; ?>
					</div>

					<button type="button" class="orke-hub-button orke-hub-button--secondary" id="orke-add-menu-item">
						<?php esc_html_e( '+ Add Menu Item', 'orkestone-agency-hub' ); ?>
					</button>

					<h3 style="margin-top:24px;"><?php esc_html_e( 'SEO Settings', 'orkestone-agency-hub' ); ?></h3>

					<div class="orke-hub-field">
						<label for="orke_title_pattern"><?php esc_html_e( 'Title Pattern', 'orkestone-agency-hub' ); ?></label>
						<input type="text" id="orke_title_pattern" name="orke_title_pattern"
							value="<?php echo isset( $saved_data['seo']['title_pattern'] ) ? esc_attr( $saved_data['seo']['title_pattern'] ) : ''; ?>"
							placeholder="<?php esc_attr_e( '%page% | Site Name', 'orkestone-agency-hub' ); ?>" />
						<p class="description"><?php esc_html_e( 'Use %page% as a placeholder for the page title.', 'orkestone-agency-hub' ); ?></p>
					</div>

					<div class="orke-hub-field">
						<label for="orke_meta_description"><?php esc_html_e( 'Default Meta Description', 'orkestone-agency-hub' ); ?></label>
						<textarea id="orke_meta_description" name="orke_meta_description" rows="3" placeholder="<?php esc_attr_e( 'A brief description of the site for search engines.', 'orkestone-agency-hub' ); ?>"><?php echo isset( $saved_data['seo']['meta_description'] ) ? esc_textarea( $saved_data['seo']['meta_description'] ) : ''; ?></textarea>
					</div>

					<div class="orke-hub-field">
						<label for="orke_og_image_id"><?php esc_html_e( 'Open Graph Image', 'orkestone-agency-hub' ); ?></label>
						<input type="url" id="orke_og_image_id" name="orke_og_image_id"
							value="<?php echo isset( $saved_data['seo']['og_image_id'] ) ? esc_attr( $saved_data['seo']['og_image_id'] ) : ''; ?>"
							placeholder="<?php esc_attr_e( 'URL to the social sharing image', 'orkestone-agency-hub' ); ?>" />
						<p class="description"><?php esc_html_e( 'URL to an image used when the site is shared on social media.', 'orkestone-agency-hub' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Actions -->
			<div class="orke-hub-card" style="display:flex;gap:12px;align-items:center;">
				<button type="submit" class="orke-hub-button orke-hub-button--primary" name="orke_action" value="calculate_budget" formaction="<?php echo esc_url( $calculate_action ); ?>" formmethod="post" id="orke-calculate-budget">
					<?php esc_html_e( 'Calculate Budget', 'orkestone-agency-hub' ); ?>
				</button>
				<button type="submit" class="orke-hub-button orke-hub-button--primary" name="orke_action" value="save_draft">
					<?php esc_html_e( 'Save Configuration', 'orkestone-agency-hub' ); ?>
				</button>
			</div>
		</form>

		<!-- Budget Breakdown -->
		<?php if ( ! empty( $budget_result ) ) : ?>
			<div class="orke-hub-card" id="orke-budget-breakdown">
				<h2><?php esc_html_e( 'Budget Breakdown', 'orkestone-agency-hub' ); ?></h2>
				<table class="orke-hub-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Component', 'orkestone-agency-hub' ); ?></th>
							<th class="numeric"><?php esc_html_e( 'Qty', 'orkestone-agency-hub' ); ?></th>
							<th class="numeric"><?php esc_html_e( 'Unit Price', 'orkestone-agency-hub' ); ?></th>
							<th class="numeric"><?php esc_html_e( 'Subtotal', 'orkestone-agency-hub' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $budget_result['items'] as $item ) : ?>
							<tr>
								<td><?php echo esc_html( $item['label'] ); ?></td>
								<td class="numeric"><?php echo esc_html( $item['qty'] ); ?></td>
								<td class="numeric"><?php echo esc_html( '$' . number_format_i18n( $item['unit_price'], 2 ) ); ?></td>
								<td class="numeric"><?php echo esc_html( '$' . number_format_i18n( $item['subtotal'], 2 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="3"><?php esc_html_e( 'Total', 'orkestone-agency-hub' ); ?></td>
							<td class="numeric"><?php echo esc_html( '$' . number_format_i18n( $budget_result['total'], 2 ) ); ?></td>
						</tr>
					</tfoot>
				</table>
			</div>
		<?php endif; ?>

		<!-- JSON Preview -->
		<?php if ( ! empty( $generated_json ) ) : ?>
			<div class="orke-hub-card" id="orke-json-preview">
				<h2><?php esc_html_e( 'Generated JSON Preview', 'orkestone-agency-hub' ); ?></h2>
				<pre class="orke-hub-json-preview"><?php echo esc_html( $generated_json ); ?></pre>
				<p class="description"><?php esc_html_e( 'This JSON will be stored as the configuration content and delivered via token upon payment.', 'orkestone-agency-hub' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>
