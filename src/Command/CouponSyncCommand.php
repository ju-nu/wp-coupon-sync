<?php
declare(strict_types=1);

namespace JUNU\Command;

use JUNU\Affiliate\AdcellNetwork;
use JUNU\Config;
use JUNU\Utils\Logger;
use PDO;
use Exception;

class CouponSyncCommand
{
    private WpCliClient $wpCli;
    private PDO $pdo;

    public function __construct()
    {
        $this->wpCli = new WpCliClient();
        $this->pdo   = $this->createPdoConnection();
    }

    /**
     * Run the coupon sync process.
     */
    public function run(): void
    {
        Logger::info("Starting coupon sync command...");

        $brands = $this->fetchBrands();
        if (empty($brands)) {
            Logger::info("No brands found. Exiting.");
            return;
        }

        foreach ($brands as $brand) {
            // Process only brands with affiliate = 'adcell'
            if (($brand['affiliate'] ?? null) !== 'adcell') {
                continue;
            }
            if (empty($brand['adcell_program_id'])) {
                Logger::warning("Brand {$brand['name']} (ID: {$brand['term_id']}) is missing ADCELL Program ID. Skipping.");
                continue;
            }

            Logger::info("Processing brand: {$brand['name']} (ID: {$brand['term_id']}) with Program ID: {$brand['adcell_program_id']}");

            // Initialize the ADCELL integration
            try {
                $adcell = new AdcellNetwork(
                    Config::getAdcellUser(),
                    Config::getAdcellApiPassword(),
                    $this->pdo
                );
            } catch (Exception $e) {
                Logger::error("Failed to initialize ADCELL network: " . $e->getMessage());
                continue;
            }

            // Fetch coupons from ADCELL
            try {
                $coupons = $adcell->fetchCoupons([$brand['adcell_program_id']]);
                Logger::info("Fetched " . count($coupons) . " coupons for brand {$brand['name']}.");
            } catch (Exception $e) {
                Logger::error("Error fetching coupons for brand {$brand['name']}: " . $e->getMessage());
                continue;
            }

            // Process each coupon
            foreach ($coupons as $coupon) {
                try {
                    $existingPostId = $this->findCouponPost($coupon['network_identifier']);
                    if ($existingPostId) {
                        Logger::info("Updating coupon post ID {$existingPostId} (network identifier: {$coupon['network_identifier']}).");
                        $this->updateCouponPost($existingPostId, $coupon);
                    } else {
                        Logger::info("Creating new coupon for network identifier: {$coupon['network_identifier']}.");
                        $newPostId = $this->createCouponPost($coupon);
                        $this->setCouponBrand($newPostId, (string)$brand['term_id']);
                    }
                } catch (Exception $e) {
                    Logger::error("Error processing coupon (network identifier: {$coupon['network_identifier']}): " . $e->getMessage());
                }
            }

            // Optionally update brand statistics (for example, average savings)
            $this->updateBrandStatistics($brand, $coupons);
        }
    }

    /**
     * Create and return a PDO connection.
     */
    private function createPdoConnection(): PDO
    {
        $dsn      = Config::getDbDsn();
        $user     = Config::getDbUser();
        $password = Config::getDbPassword();
        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Retrieves brands (coupon_store terms with ACF fields) from WordPress via WP‑CLI.
     *
     * @return array
     * @throws Exception
     */
    private function fetchBrands(): array
    {
        $phpCode = <<<'PHP'
$terms = get_terms([
    'taxonomy'   => 'coupon_store',
    'hide_empty' => false,
]);
$brands = [];
if (!empty($terms) && !is_wp_error($terms)) {
    foreach ($terms as $term) {
        $brands[] = [
            'term_id'           => $term->term_id,
            'name'              => $term->name,
            'affiliate'         => get_field('junu_affiliate', $term),
            'adcell_program_id' => get_field('aff_id_adcell', $term),
        ];
    }
}
echo json_encode($brands);
PHP;
        $command = 'eval ' . escapeshellarg($phpCode);
        $output  = $this->wpCli->runCommand($command);
        $brands  = json_decode($output, true);
        if ($brands === null) {
            throw new Exception("Failed to decode brands JSON: " . $output);
        }
        return $brands;
    }

    /**
     * Searches for an existing coupon post by its network identifier.
     *
     * @param string $networkIdentifier
     * @return string|null
     * @throws Exception
     */
    private function findCouponPost(string $networkIdentifier): ?string
    {
        $phpCode = '
$args = [
    "post_type"      => "coupon",
    "meta_key"       => "network_identifier",
    "meta_value"     => ' . var_export($networkIdentifier, true) . ',
    "posts_per_page" => 1,
    "fields"         => "ids"
];
$posts = get_posts($args);
if (!empty($posts)) {
    echo $posts[0];
}
';
        $command = 'eval ' . escapeshellarg($phpCode);
        $output  = $this->wpCli->runCommand($command);
        $postId  = trim($output);
        return $postId !== '' ? $postId : null;
    }

    /**
     * Creates a new coupon post.
     *
     * @param array $coupon
     * @return string The new coupon post ID.
     * @throws Exception
     */
    private function createCouponPost(array $coupon): string
    {
        $title   = $coupon['title'];
        $content = $coupon['information'];
        $cmd = sprintf(
            'post create --post_type=coupon --post_title=%s --post_content=%s --post_status=publish --porcelain',
            escapeshellarg($title),
            escapeshellarg($content)
        );
        $output = $this->wpCli->runCommand($cmd);
        return trim($output);
    }

    /**
     * Updates an existing coupon post.
     *
     * @param string $postId
     * @param array $coupon
     * @throws Exception
     */
    private function updateCouponPost(string $postId, array $coupon): void
    {
        $title   = $coupon['title'];
        $content = $coupon['information'];
        $cmd = sprintf(
            'post update %s --post_title=%s --post_content=%s',
            escapeshellarg($postId),
            escapeshellarg($title),
            escapeshellarg($content)
        );
        $this->wpCli->runCommand($cmd);
        $this->updateCouponMeta($postId, $coupon);
    }

    /**
     * Updates coupon post meta fields.
     *
     * @param string $postId
     * @param array $coupon
     * @throws Exception
     */
    private function updateCouponMeta(string $postId, array $coupon): void
    {
        $metaFields = [
            'coupon_code'         => $coupon['code'],
            'coupon_url'          => $coupon['url'],
            'start_time'          => $coupon['start_time'],
            'end_time'            => $coupon['end_time'],
            'total_shopping_cart' => $coupon['total_shopping_cart'],
            'total_commission'    => $coupon['total_commission'],
            'network_identifier'  => $coupon['network_identifier'],
        ];

        foreach ($metaFields as $key => $value) {
            $cmd = sprintf(
                'post meta update %s %s %s',
                escapeshellarg($postId),
                escapeshellarg($key),
                escapeshellarg($value)
            );
            $this->wpCli->runCommand($cmd);
        }
    }

    /**
     * Associates a coupon post with a brand (term in coupon_store).
     *
     * @param string $postId
     * @param string $brandTermId
     * @throws Exception
     */
    private function setCouponBrand(string $postId, string $brandTermId): void
    {
        $cmd = sprintf(
            'post term set %s coupon_store %s',
            escapeshellarg($postId),
            escapeshellarg($brandTermId)
        );
        $this->wpCli->runCommand($cmd);
    }

    /**
     * Optionally updates brand statistics (e.g. average savings) based on the fetched coupons.
     *
     * @param array $brand
     * @param array $coupons
     * @throws Exception
     */
    private function updateBrandStatistics(array $brand, array $coupons): void
    {
        $sum   = 0;
        $count = 0;
        foreach ($coupons as $coupon) {
            if (isset($coupon['amount']) && is_numeric($coupon['amount'])) {
                $sum   += (float)$coupon['amount'];
                $count++;
            }
        }
        $avg = ($count > 0) ? $sum / $count : 0;
        $cmd = sprintf(
            'term meta update coupon_store %s avg_savings %s',
            escapeshellarg($brand['term_id']),
            escapeshellarg((string)$avg)
        );
        $this->wpCli->runCommand($cmd);
    }
}
