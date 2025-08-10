<h1>Secret Server</h1>

<p>Egyszerű REST API titkok ideiglenes tárolására és megosztására. 
A titok tartalma <strong>titkosítva</strong> kerül az adatbázisba, nem tárolunk plaintextet.</p>

<hr />

<h2>Funkciók</h2>
<ul>
  <li>Titok létrehozása megadott <code>expireAfterViews</code> és <code>expireAfter</code> paraméterekkel</li>
  <li>Titok lekérése <code>hash</code> alapján</li>
  <li>Automatikus elérhetetlenné válás lejáratkor vagy megtekintésszám kimerülésekor</li>
  <li>Válasz formátuma <code>Accept</code> fejléctől függően JSON vagy XML</li>
</ul>

<h2>Követelmények</h2>
<ul>
  <li>PHP 8.2+</li>
  <li>MySQL 5.7+/8.0+</li>
  <li>Composer</li>
  <li>OpenSSL PHP kiterjesztés</li>
</ul>

<h2>Futtatás</h2>
<pre><code>php yii serve --port=8080
# vagy saját Apache/Nginx VirtualHost, DocumentRoot: web/</code></pre>

<h2>API használat</h2>

<h3>POST /v1/secret</h3>
<p><em>application/x-www-form-urlencoded</em> űrlapmezők:</p>
<ul>
  <li><code>secret</code> (string, kötelező)</li>
  <li><code>expireAfterViews</code> (int &gt; 0, kötelező)</li>
  <li><code>expireAfter</code> (int ≥ 0, perc; 0 = sosem jár le idő alapján, kötelező)</li>
</ul>

<h4>Példa (curl)</h4>
<pre><code>curl -X POST "http://secretserver.lhost/v1/secret" \
  -H "Accept: application/json" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data "secret=titkos%20szoveg&amp;expireAfterViews=2&amp;expireAfter=5"</code></pre>

<h3>GET /v1/secret/{hash}</h3>
<p>Visszaadja a titkot és 1-gyel csökkenti a megtekintések számát. Lejárat vagy 0 megtekintés esetén 404.</p>

<h4>Példa (curl)</h4>
<pre><code>curl -X GET "http://secretserver.lhost/v1/secret/&lt;hash&gt;" \
  -H "Accept: application/xml"</code></pre>

<h2>Swagger UI</h2>
<ul>
  <li>UI: <code>/docs/</code> (statikus Swagger UI)</li>
  <li>Spec: <code>/swagger.yaml</code></li>
</ul>

<h2>Biztonság</h2>
<ul>
  <li>Titkosítás: AES-256-GCM (mezők: <code>ciphertext</code>, <code>iv</code>, <code>tag</code>, <code>alg</code>)</li>
  <li>A kulcs Base64 formában: <code>SECRET_KEY_BASE64</code> (32 bájt dekódolva)</li>
  <li>Plaintext tartalom nem kerül az adatbázisba és nem íródik logba</li>
</ul>

<h2>Megjegyzések</h2>
<ul>
  <li>JSON/XML válasz az <code>Accept</code> fejléctől függ</li>
  <li>Időpontok UTC ISO-8601 formátumban</li>
  <li>Hash URL-barát Base64 (</code>-</code>, <code>_</code> karakterek megengedettek)</li>
</ul>
