<?php
/** Custom production is a quote service; catalog records remain untouched. */
class KafkaCustomProduction
{
    private $module;
    private $context;

    public function __construct($module)
    {
        $this->module = $module;
        $this->context = Context::getContext();
    }

    public function render(int $categoryId): string
    {
        $service = null;
        foreach ($this->module->sections() as $section) {
            foreach ($section['items'] as $branch) {
                if ($branch['key'] !== 'vlastni-potisk') { continue; }
                foreach ($branch['items'] as $item) {
                    if ((int) ($item['category_id'] ?? 0) === $categoryId) { $service = $item; }
                }
            }
        }
        if (!$service) { return ''; }
        $category = new Category($categoryId, $this->context->language->id);
        if (!$category->active || !$category->checkAccess((int) $this->context->customer->id)) { return ''; }
        $cookie = $this->context->cookie;
        if (empty($cookie->kafka_quote_token)) { $cookie->kafka_quote_token = bin2hex(random_bytes(32)); }
        $state = ['error' => '', 'success' => '', 'email' => '', 'message' => ''];
        if (Tools::isSubmit('kafka_quote_submit')) { $state = $this->submit($category); }
        // Use imported price-list descriptions, never the product card's unrelated base price.
        $rows = Db::getInstance()->executeS(
            'SELECT pl.name, pl.description_short, pl.description FROM `' . _DB_PREFIX_ . 'category_product` cp'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product=cp.id_product'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product=cp.id_product AND pl.id_shop=ps.id_shop'
            . ' WHERE cp.id_category=' . $categoryId . ' AND ps.active=1 AND ps.id_shop=' . (int) $this->context->shop->id
            . ' AND pl.id_lang=' . (int) $this->context->language->id . ' ORDER BY cp.position, cp.id_product'
        );
        foreach ($rows as &$row) {
            $row['description_short'] = Tools::purifyHTML($row['description_short']);
            $row['description'] = Tools::purifyHTML($row['description']);
        }
        unset($row);
        $this->context->smarty->assign([
            'kafka_service' => $service, 'kafka_price_lists' => $rows, 'kafka_quote' => $state,
            'kafka_quote_token' => $cookie->kafka_quote_token,
            'kafka_quote_url' => $this->context->link->getCategoryLink($category),
        ]);
        return $this->module->fetch('module:kafkahome/views/templates/hook/custom-production.tpl');
    }

    private function submit(Category $category): array
    {
        $state = ['error' => '', 'success' => '', 'email' => trim((string) Tools::getValue('quote_email', '')),
            'message' => trim((string) Tools::getValue('quote_message', ''))];
        $cookie = $this->context->cookie;
        if (!hash_equals((string) $cookie->kafka_quote_token, (string) Tools::getValue('quote_token', ''))) {
            $state['error'] = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
        } elseif (Tools::getValue('quote_website') || time() - (int) $cookie->kafka_quote_last < 60) {
            $state['error'] = 'Před další poptávkou prosím chvíli počkejte.';
        } elseif (!Validate::isEmail($state['email']) || strlen($state['email']) > 254) {
            $state['error'] = 'Vyplňte platný kontaktní e-mail.';
        } elseif (mb_strlen($state['message']) > 5000) {
            $state['error'] = 'Zadání může mít nejvýše 5 000 znaků.';
        }
        $file = $_FILES['quote_file'] ?? [];
        $mime = '';
        if (!$state['error']) {
            $allowed = ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg']];
            $ext = strtolower(pathinfo(is_string($file['name'] ?? null) ? $file['name'] : '', PATHINFO_EXTENSION));
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')
                || ($file['size'] ?? 0) < 1 || $file['size'] > 10 * 1024 * 1024) {
                $state['error'] = 'Nahrajte soubor PDF, PNG nebo JPG, nejvýše 10 MB.';
            } else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) {
                    $state['error'] = 'Soubor nemá podporovaný formát. Použijte skutečné PDF, PNG nebo JPG.';
                }
            }
        }
        if ($state['error']) { return $state; }
        $attachment = ['content' => file_get_contents($file['tmp_name']), 'name' => 'motiv.' . $ext, 'mime' => $mime];
        $subject = 'Poptávka vlastní výroby: ' . $category->name;
        $message = 'Služba: ' . $category->name . "\nKontakt: " . $state['email'] . "\n\nZadání:\n" . $state['message'];
        $local = in_array($this->context->shop->domain, ['localhost:8083', 'localhost:8080'], true);
        $sent = false;
        try {
            // Shared per-IP throttle also covers new sessions; hashes contain no contact data.
            $limitDirectory = sys_get_temp_dir() . '/kafka-quote-limits';
            if (!is_dir($limitDirectory) && !mkdir($limitDirectory, 0700, true)) { throw new RuntimeException('Rate limit unavailable'); }
            $limit = fopen($limitDirectory . '/' . hash_hmac('sha256', (string) Tools::getRemoteAddr(), _COOKIE_KEY_), 'c+');
            if (!$limit || !flock($limit, LOCK_EX)) { throw new RuntimeException('Rate limit unavailable'); }
            try {
                if (time() - (int) stream_get_contents($limit) < 60) {
                    $state['error'] = 'Před další poptávkou prosím chvíli počkejte.';
                    return $state;
                }
                ftruncate($limit, 0);
                rewind($limit);
                fwrite($limit, (string) time());
            } finally { flock($limit, LOCK_UN); fclose($limit); }
            if ($local) {
                // Isolated development never sends externally. Private, ephemeral RFC822 outbox.
                $directory = sys_get_temp_dir() . '/kafka-custom-quotes';
                if (!is_dir($directory) && !mkdir($directory, 0700, true)) { throw new RuntimeException('Outbox unavailable'); }
                $boundary = bin2hex(random_bytes(20));
                $eml = 'To: ' . Configuration::get('PS_SHOP_EMAIL') . "\r\nReply-To: " . $state['email']
                    . "\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nMIME-Version: 1.0\r\n"
                    . 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n\r\n--" . $boundary
                    . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                    . chunk_split(base64_encode($message)) . '--' . $boundary . "\r\nContent-Type: " . $mime
                    . "\r\nContent-Disposition: attachment; filename=\"" . $attachment['name'] . "\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                    . chunk_split(base64_encode($attachment['content'])) . '--' . $boundary . "--\r\n";
                $path = $directory . '/' . bin2hex(random_bytes(16)) . '.eml';
                $sent = file_put_contents($path, $eml, LOCK_EX) !== false;
                if ($sent) { chmod($path, 0600); }
            } elseif ((int) Configuration::get('PS_MAIL_METHOD') !== 3) {
                $sent = (bool) Mail::Send((int) $this->context->language->id, 'custom_production', $subject,
                    ['{quote_message}' => nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')), '{quote_text}' => $message],
                    Configuration::get('PS_SHOP_EMAIL'), null, null, null, $attachment, null,
                    $this->module->getLocalPath() . 'mails/', false, (int) $this->context->shop->id, null, $state['email']);
            }
        } catch (Throwable $e) {
            // Never expose submitted data, mail configuration or filesystem paths to the browser.
            $sent = false;
        }
        if ($sent) {
            $cookie->kafka_quote_last = time();
            $cookie->kafka_quote_token = bin2hex(random_bytes(32));
            $state['success'] = $local
                ? 'Testovací poptávka s přílohou byla uložena v místní schránce. Z tohoto náhledu se e-maily neodesílají.'
                : 'Poptávka s přílohou byla odeslána. Ozveme se vám na uvedený e-mail s kalkulací.';
            $state['email'] = $state['message'] = '';
        } else {
            $state['error'] = 'Poptávku se nepodařilo odeslat. Zkuste to prosím později. Soubor bude potřeba vybrat znovu.';
        }
        return $state;
    }
}
