<?php
/**
 * Title: Homburg — Portaalkaarten-sectie
 * Slug: homburg/portaalkaarten-sectie
 * Categories: homburg
 * Description: De vier hoofdkaarten (Webshop, Productconfigurator, Downloads, Content) in één groep met de class hdp-kaarten-sectie, zodat ze alleen voor ingelogde, goedgekeurde dealers zichtbaar zijn. Webshop en configurator halen hun URL uit Instellingen > Dealerportaal.
 * Keywords: portaal, kaarten, dealer, webshop, downloads
 */
?>
<!-- wp:group {"className":"hdp-kaarten-sectie","layout":{"type":"constrained"}} -->
<div class="wp-block-group hdp-kaarten-sectie">
	<!-- wp:homburg/portaal-kaart {"icoon":"webshop","titel":"Webshop","tekst":"Bestel onderdelen en machines in de dealerwebshop.","knoptekst":"Naar de webshop","instellingSleutel":"webshop_url"} /-->
	<!-- wp:homburg/portaal-kaart {"icoon":"configurator","titel":"Productconfigurator","tekst":"Stel een machine samen en vraag direct een offerte aan.","knoptekst":"Configurator openen","instellingSleutel":"configurator_url","nieuweTab":true} /-->
	<!-- wp:homburg/portaal-kaart {"icoon":"downloads","titel":"Downloads","tekst":"Handleidingen, brochures en technische documentatie.","knoptekst":"Naar downloads","url":"/downloads/"} /-->
	<!-- wp:homburg/portaal-kaart {"icoon":"content","titel":"Content","tekst":"Marketingmateriaal, afbeeldingen en video's voor dealers.","knoptekst":"Naar content","url":"/content/"} /-->
</div>
<!-- /wp:group -->
