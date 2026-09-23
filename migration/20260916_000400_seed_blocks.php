<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Seeds the 10 fixed blocks and their FR/NL/EN translations from the
 * original ../redan static site. Asset URLs are rewritten to the root-level
 * flat form the skeleton Media layer expects (/assets/img/x.webp → /x.webp).
 *
 * The mailto links in rallye/training/youth use __CONTACT_EMAIL__ as a
 * placeholder; the Twig partials replace it with the setting value at render
 * time (spec/02).
 *
 * announcement is seeded hidden (visible=0) so the admin can activate it when
 * needed.
 */

use \Skeleton\Database\Database;

class Migration_20260916_000400_Seed_Blocks extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$language_ids = [];
		$rows = $db->get_all('SELECT id, name_short FROM language');
		foreach ($rows as $row) {
			$language_ids[$row['name_short']] = (int)$row['id'];
		}

		$blocks = $this->get_block_definitions();

		foreach ($blocks as $index => $block) {
			$db->query("
				INSERT INTO `block` (`type`, `anchor`, `sort_order`, `visible`, `created`)
				VALUES (?, ?, ?, ?, NOW())
			", [
				$block['type'],
				$block['anchor'],
				$block['sort_order'],
				$block['visible'],
			]);

			$block_id = (int)$db->get_one('SELECT LAST_INSERT_ID();');

			foreach ($block['translations'] as $lang_short => $translation) {
				if (!isset($language_ids[$lang_short])) {
					throw new \Exception('Language "' . $lang_short . '" not found in language table.');
				}
				$db->query("
					INSERT INTO `block_translation` (`block_id`, `language_id`, `title`, `body`, `created`)
					VALUES (?, ?, ?, ?, NOW())
				", [
					$block_id,
					$language_ids[$lang_short],
					$translation['title'],
					$translation['body'],
				]);
			}
		}
	}

	/**
	 * Migrate down
	 *
	 * Drops all seeded block_translation and block rows.
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();
		$db->query('DELETE FROM `block_translation`;');
		$db->query('DELETE FROM `block`;');
	}

	/**
	 * Return the full block seed data
	 *
	 * @access private
	 * @return array
	 */
	private function get_block_definitions(): array {
		return [
			// 1 — masthead
			[
				'type' => 'masthead',
				'anchor' => '',
				'sort_order' => 1,
				'visible' => 1,
				'translations' => [
					'fr' => [
						'title' => '',
						'body' => 'Club de spéléologie situé à Bruxelles, disposant d\'un local d\'entraînement situé sous la Basilique',
					],
					'nl' => [
						'title' => '',
						'body' => 'Speleoclub in Brussel, met een trainingsruimte onder de basiliek',
					],
					'en' => [
						'title' => '',
						'body' => 'Caving club located in Brussels, with a training facility underneath the Basilica.',
					],
				],
			],

			// 2 — club
			[
				'type' => 'club',
				'anchor' => 'about',
				'sort_order' => 2,
				'visible' => 1,
				'translations' => [
					'fr' => [
						'title' => '',
						'body' => '<h2 class="text-white mb-4">Le Club</h2><p class="text-white">Le Groupe Spéléo du Redan est l\'un des grands clubs de spéléologie de Belgique. Le club dispose d\'une salle d\'entraînement située dans les caves de la Basilique de Koekelberg et gère également un programme de formation destiné aux jeunes. Le G.S. Redan est particulièrement connu pour son rallye qu\'il organise tous les 3 ou 4 ans, au cours duquel plusieurs parcours de cordes sont installés sur et dans toute la Basilique. Des spéléologues de toute l\'Europe viennent participer à cet événement unique. </p>',
					],
					'nl' => [
						'title' => '',
						'body' => '<h2 class="text-white mb-4">De club</h2><p class="text-white">Groupe Spéléo du Redan is één van de grootste speleologieclubs van België. De club beschikt over een trainingszaal onder de Basiliek van Koekelberg en heeft ook een actieve jongerenwerking. Redan staat vooral bekend om de Rally, waarbij over de hele basiliek verschillende touwparcours worden opgebouwd. Speleologen uit heel Europa nemen hieraan deel. </p>',
					],
					'en' => [
						'title' => '',
						'body' => '<h2 class="text-white mb-4">The Club</h2><p class="text-white">Groupe Spéléo du Redan is one of the largest caving clubs in Belgium. The club has a training facility located in the basement of the Koekelberg Basilica and also runs an active youth program. Redan is especially known for the Rally they organise, during which rope courses are rigged over the entire basilica. Cavers from all over Europe come to take part in this unique event. </p>',
					],
				],
			],

			// 3 — rallye
			[
				'type' => 'rallye',
				'anchor' => 'rallye',
				'sort_order' => 3,
				'visible' => 1,
				'translations' => [
					'fr' => [
						'title' => 'Rallye 2027',
						'body' => '<span class="rallye-badge text-uppercase">Save the date</span><h3 class="rallye-title my-3">Rallye spéléo de la Basilique</h3><p class="rallye-date mb-3">10 / 11 / 12 septembre 2027</p><hr class="my-3 mx-auto"/><p class="text-black mb-3">Tous les trois ou quatre ans, GS Redan installe des parcours de cordes sur et dans la Basilique : trois itinéraires de difficulté croissante, plus de 2 000 mètres de corde et une vue exceptionnelle jusqu\'à l\'Atomium. Environ 250 participants venus de toute l\'Europe avaient pris part à la dernière édition.</p><p class="text-black mb-3">L\'événement est réservé aux spéléologues confirmés, mais le public est le bienvenu pour admirer le spectacle. Les inscriptions ouvriront dès que l\'organisation sera finalisée.</p><p class="text-black mb-0"><strong>Questions ?</strong> <a href="mailto:__CONTACT_EMAIL__?subject=Rallye 2027">__CONTACT_EMAIL__</a></p>',
					],
					'nl' => [
						'title' => 'Rallye 2027',
						'body' => '<span class="rallye-badge text-uppercase">Save the date</span><h3 class="rallye-title my-3">Speleorallye in de Basiliek</h3><p class="rallye-date mb-3">10 / 11 / 12 september 2027</p><hr class="my-3 mx-auto"/><p class="text-black mb-3">Om de drie à vier jaar bouwt GS Redan, touwparcours op in en rond de Basiliek: drie parcours met toenemende moeilijkheidsgraad, meer dan 2 000 meter touw en een spectaculair zicht tot aan het Atomium. Zo\'n 250 deelnemers uit heel Europa namen deel aan de vorige editie.</p><p class="text-black mb-3">Het evenement is bedoeld voor ervaren speleologen; toeschouwers zijn van harte welkom. De inschrijvingen openen zodra alles definitief geregeld is.</p><p class="text-black mb-0"><strong>Vragen?</strong> <a href="mailto:__CONTACT_EMAIL__?subject=Rallye 2027">__CONTACT_EMAIL__</a></p>',
					],
					'en' => [
						'title' => 'Rally 2027',
						'body' => '<span class="rallye-badge text-uppercase">Save the date</span><h3 class="rallye-title my-3">Basilica caving rally</h3><p class="rallye-date mb-3">10 / 11 / 12 September 2027</p><hr class="my-3 mx-auto"/><p class="text-black mb-3">Once every three or four years, GS Redan rigs rope courses on and inside the Basilica: three routes of increasing difficulty, more than 2,000 metres of rope and a spectacular view all the way to the Atomium. Around 250 participants from across Europe took part in the last edition.</p><p class="text-black mb-3">The event is open to experienced cavers; spectators are welcome to come and watch. Registration will open as soon as everything has been finalised.</p><p class="text-black mb-0"><strong>Questions?</strong> <a href="mailto:__CONTACT_EMAIL__?subject=Rally 2027">__CONTACT_EMAIL__</a></p>',
					],
				],
			],

			// 4 — rallye_gallery
			[
				'type' => 'rallye_gallery',
				'anchor' => '',
				'sort_order' => 4,
				'visible' => 1,
				'translations' => [
					'fr' => [
						'title' => '',
						'body' => '<div class="col-lg-4"><img class="img-fluid rounded" src="/rallye-1.webp?v=11" width="800" height="533" loading="lazy" decoding="async" alt="Spéléo se rappelant à un nœud lors du rallye 2023, photo 1"/></div><div class="col-lg-4"><img class="img-fluid rounded" src="/rallye-2.webp?v=11" width="800" height="533" loading="lazy" decoding="async" alt="Parcours de cordes sur la Basilique lors du rallye 2023, photo 2"/></div><div class="col-lg-4"><img class="img-fluid rounded" src="/rallye-3.webp?v=11" width="800" height="533" loading="lazy" decoding="async" alt="Spéléologues sur la façade de la Basilique lors du rallye 2023, photo 3"/></div>',
					],
					'nl' => [
						'title' => '',
						'body' => '<div class="col-lg-4"><img class="img-fluid rounded" src="/rallye-1.webp?v=11" width="800" height="533" loading="lazy" decoding="async" alt="Speleoloog die een prusik knoopt tijdens de rally 2023, foto 1"/></div><div class="col-lg-4"><img class="img-fluid rounded" src="/rallye-2.webp?v=11" width="800" height="533" loading="lazy" decoding="async" alt="Touwparcours over de Basiliek tijdens de rally 2023, foto 2"/></div><div class="col-lg-4"><img class="img-fluid rounded" src="/rallye-3.webp?v=11" width="800" height="533" loading="lazy" decoding="async" alt="Speleologen aan de gevel van de Basiliek tijdens de rally 2023, foto 3"/></div>',
					],
					'en' => [
						'title' => '',
						'body' => '<div class="col-lg-4"><img class="img-fluid rounded" src="/rallye-1.webp?v=11" width="800" height="533" loading="lazy" decoding="async" alt="Caver prussik-ing up a knot at the 2023 rally, photo 1"/></div><div class="col-lg-4"><img class="img-fluid rounded" src="/rallye-2.webp?v=11" width="800" height="533" loading="lazy" decoding="async" alt="Rope courses on the Basilica at the 2023 rally, photo 2"/></div><div class="col-lg-4"><img class="img-fluid rounded" src="/rallye-3.webp?v=11" width="800" height="533" loading="lazy" decoding="async" alt="Cavers on the facade of the Basilica at the 2023 rally, photo 3"/></div>',
					],
				],
			],

			// 5 — salle
			[
				'type' => 'salle',
				'anchor' => 'salle',
				'sort_order' => 5,
				'visible' => 1,
				'translations' => [
					'fr' => [
						'title' => 'La Salle',
						'body' => '<h3>La salle d\'entraînement</h3><hr class="my-3 mx-auto"/><p class="text-black mb-4">Séances d\'entraînement les <span class="accent-strong">lundis impairs</span>, de <span class="accent-strong">19h30 à 22h30</span></p><p class="text-black mb-4"><span class="accent-strong">Ouvertures <span class="current-year">2026</span></span> :<br><span class="salle-openings">7 et 21 Septembre<br>5 Octobre<br>9 et 23 Novembre<br>7 Décembre</span></p><p class="text-black mb-0"><strong>ATTENTION!</strong> Présentation obligatoire de votre carte de membre UBS, VVS ou CAB nous garantissant une assurance pour cette activité. PAF&nbsp;:&nbsp;5 €</p>',
					],
					'nl' => [
						'title' => 'De zaal',
						'body' => '<h3>De zaal</h3><hr class="my-3 mx-auto"/><p class="text-black mb-4">Trainingsessies op <span class="accent-strong">oneven maandagen</span>, van <span class="accent-strong">19u30 tot 22u30</span></p><p class="text-black mb-4"><span class="accent-strong">Open op <span class="current-year">2026</span></span> :<br><span class="salle-openings">7 en 21 September<br>5 Oktober<br>9 en 23 November<br>7 December</span></p><p class="text-black mb-0"><strong>OPGELET!</strong> Verplichte vertoning van uw UBS-, VVS- of CAB-lidkaart zo zijn we zeker dat u verzekerd bent voor deze activiteit. Kost: € 5</p>',
					],
					'en' => [
						'title' => 'Training Facility',
						'body' => '<h3>Training Facility</h3><hr class="my-3 mx-auto"/><p class="text-black mb-4">Training sessions on <span class="accent-strong">odd Mondays</span>, from <span class="accent-strong">7.30 pm to 10.30 pm</span></p><p class="text-black mb-4"><span class="accent-strong">Openings <span class="current-year">2026</span></span> :<br><span class="salle-openings">7th and 21st of September<br>5th of October<br>9th and 23rd of November<br>7th of December</span></p><p class="text-black mb-0"><strong>ATTENTION!</strong> You must present your UBS, VVS or CAB membership card, which guarantees insurance coverage for this activity. Participation fee: €5</p>',
					],
				],
			],

			// 6 — training
			[
				'type' => 'training',
				'anchor' => 'projects',
				'sort_order' => 6,
				'visible' => 1,
				'translations' => [
					'fr' => [
						'title' => 'Projets',
						'body' => '<h3 class="text-white">Entraînement</h3><p class="mb-0 text-white">Les membres du club peuvent s\'entraîner tous les mardis soirs à partir de 20h00 dans la salle située sous la basilique. Deux camps sont également organisés chaque année, pendant les vacances d\'automne et de printemps francophones. Pour plus d\'informations ou pour devenir membre du club, veuillez contacter : <a class="light-link" href="mailto:__CONTACT_EMAIL__?subject=Entraînement">__CONTACT_EMAIL__</a>.</p>',
					],
					'nl' => [
						'title' => 'Projecten',
						'body' => '<h3 class="text-white">Clubwerking</h3><p class="mb-0 text-white">Leden van de club kunnen elke dinsdagavond vanaf 20.00 uur trainen in de zaal onder de basiliek. Daarnaast worden er twee kampen per jaar georganiseerd, tijdens de Franstalige herfst- en lentevakantie. Voor meer informatie of om lid te worden van de club, contacteer: <a class="light-link" href="mailto:__CONTACT_EMAIL__?subject=Training">__CONTACT_EMAIL__</a>.</p>',
					],
					'en' => [
						'title' => 'Projects',
						'body' => '<h3 class="text-white">Club Activities</h3><p class="mb-0 text-white">Club members can train every Tuesday evening from 8:00 PM in the training facility beneath the basilica. In addition, two camps are organised each year during the Autumn and Spring holidays (French-speaking school terms). For more information or to become a member, please contact : <a class="light-link" href="mailto:__CONTACT_EMAIL__?subject=Training">__CONTACT_EMAIL__</a></p>',
					],
				],
			],

			// 7 — youth
			[
				'type' => 'youth',
				'anchor' => '',
				'sort_order' => 7,
				'visible' => 1,
				'translations' => [
					'fr' => [
						'title' => '',
						'body' => '<h3 class="text-white">Activités pour les jeunes</h3><p class="mb-0 text-white ">Chaque mercredi après-midi pendant l\'année scolaire, le Groupe Spéléo du Redan organise des cours de spéléologie spécialement destinés aux jeunes. Les jeunes qui maîtrisent suffisamment les techniques peuvent participer aux camps organisés pendant les vacances d\'automne et de printemps francophones. Pour plus d\'informations ou pour vous inscrire aux activités pour les jeunes, contactez : <a class="light-link" href="mailto:__CONTACT_EMAIL__?subject=Acitivités Jeunes">__CONTACT_EMAIL__</a></p>',
					],
					'nl' => [
						'title' => '',
						'body' => '<h3 class="text-white">Jongerenwerking</h3><p class="mb-0 text-white ">Elke woensdagnamiddag tijdens het schooljaar organiseert Groupe Spéléo du Redan speleolessen die specifiek gericht zijn op jongeren. Jongeren die de technieken voldoende beheersen, kunnen deelnemen aan de kampen tijdens de Franstalige herfst- en lentevakantie. Voor meer informatie of om in te schrijven voor de jongerenwerking, contacteer : <a class="light-link" href="mailto:__CONTACT_EMAIL__?subject=Jongerenwerking">__CONTACT_EMAIL__</a></p>',
					],
					'en' => [
						'title' => '',
						'body' => '<h3 class="text-white">Youth program</h3><p class="mb-0 text-white ">Groupe Spéléo du Redan offers caving lessons with an emphasis on SRT for youth every Wednesday afternoon during the school year. Young participants who have mastered the basic techniques are invited to join the club\'s camps during the Autumn and Spring holidays (according to the French-speaking school calendar). For more information or to register for the youth program, please contact : <a class="light-link" href="mailto:__CONTACT_EMAIL__?subject=Youth Program">__CONTACT_EMAIL__</a></p>',
					],
				],
			],

			// 8 — announcement (hidden by default)
			[
				'type' => 'announcement',
				'anchor' => 'announcement',
				'sort_order' => 8,
				'visible' => 0,
				'translations' => [
					'fr' => [
						'title' => 'Annonce',
						'body' => '',
					],
					'nl' => [
						'title' => 'Aankondiging',
						'body' => '',
					],
					'en' => [
						'title' => 'Announcement',
						'body' => '',
					],
				],
			],

			// 9 — contact (fully settings-driven)
			[
				'type' => 'contact',
				'anchor' => 'contact',
				'sort_order' => 9,
				'visible' => 1,
				'translations' => [
					'fr' => [
						'title' => 'Contact',
						'body' => '',
					],
					'nl' => [
						'title' => 'Contact',
						'body' => '',
					],
					'en' => [
						'title' => 'Contact',
						'body' => '',
					],
				],
			],

			// 10 — footer (fully settings-driven)
			[
				'type' => 'footer',
				'anchor' => '',
				'sort_order' => 10,
				'visible' => 1,
				'translations' => [
					'fr' => [
						'title' => '',
						'body' => '',
					],
					'nl' => [
						'title' => '',
						'body' => '',
					],
					'en' => [
						'title' => '',
						'body' => '',
					],
				],
			],
		];
	}
}