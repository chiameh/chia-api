<?php

namespace BrooksYang\ChiaApi;

use BrooksYang\ChiaApi\Provider\HttpProviderInterface;
use BrooksYang\ChiaApi\Exception\ChiaException;
use BrooksYang\ChiaUtils\ChiaUtils;
use BrooksYang\ChiaUtils\Exception\ChiaUtilsException;

/**
 * A PHP API for interacting with the Chia Network (XCH)
 *
 * @package BrooksYang\ChiaAPI
 */
class Chia
{
    use ChiaAwareTrait;

    /**
     * Default block
     *
     * @var string|integer|bool
     */
    protected $defaultBlock = 'latest';

    /**
     * Provider manager
     *
     * @var ChiaManager
     */
    protected $manager;

    /**
     * Object Result
     *
     * @var bool
     */
    protected $isObject = false;

    /**
     * Chia constructor.
     *
     * @param HttpProviderInterface|null $fullNode
     * @param HttpProviderInterface|null $walletServer
     * @throws ChiaException
     */
    public function __construct(?HttpProviderInterface $fullNode = null, ?HttpProviderInterface $walletServer = null)
    {
        $this->setManager(new ChiaManager($this, [
            'fullNode'     => $fullNode,
            'walletServer' => $walletServer,
        ]));
    }

    /**
     * Create a new instance if the value isn't one already.
     *
     * @param HttpProviderInterface|null $fullNode
     * @param HttpProviderInterface|null $walletServer
     * @return static
     * @throws ChiaException
     */
    public static function make(?HttpProviderInterface $fullNode = null, ?HttpProviderInterface $walletServer = null)
    {
        return new static($fullNode, $walletServer);
    }

    /**
     * Laravel
     *
     * @return Chia
     */
    public function getFacade(): Chia
    {
        return $this;
    }

    /**
     * Enter the link to the manager nodes
     *
     * @param $providers
     */
    public function setManager($providers)
    {
        $this->manager = $providers;
    }

    /**
     * Get provider manager
     *
     * @return ChiaManager
     */
    public function getManager(): ChiaManager
    {
        return $this->manager;
    }

    /**
     * Set is object
     *
     * @param bool $value
     * @return Chia
     */
    public function setIsObject(bool $value)
    {
        $this->isObject = boolval($value);
        return $this;
    }

    /**
     * Check connected provider
     *
     * @param $provider
     * @return bool
     */
    public function isValidProvider($provider): bool
    {
        return ($provider instanceof HttpProviderInterface);
    }

    /**
     * Enter the default block
     *
     * @param bool $blockID
     * @throws ChiaException
     */
    public function setDefaultBlock($blockID = false): void
    {
        if ($blockID === false || $blockID == 'latest' || $blockID == 'earliest' || $blockID === 0) {
            $this->defaultBlock = $blockID;
            return;
        }

        if (!is_integer($blockID)) {
            throw new ChiaException('Invalid block ID provided');
        }

        $this->defaultBlock = abs($blockID);
    }

    /**
     * Get default block
     *
     * @return string|integer|bool
     */
    public function getDefaultBlock()
    {
        return $this->defaultBlock;
    }

    /**
     * Enter your private account key
     *
     * @param string $crt
     * @param string $privateKey
     */
    public function setPrivateKey(string $crt, string $privateKey): void
    {
        $this->crt = $crt;
        $this->privateKey = $privateKey;
    }

    /**
     * Get customized provider data
     *
     * @return array
     */
    public function providers(): array
    {
        return $this->manager->getProviders();
    }


    /*
    |--------------------------------------------------------------------------
    | FULL NODE API
    |--------------------------------------------------------------------------
    |
    | Here is the full node api for chia.
    |
    */

    /**
     * Check Connection Providers
     *
     * @return array
     */
    public function isConnected(): array
    {
        return $this->manager->isConnected();
    }

    /**
     * Get Blockchain State
     *
     * @return array
     * @throws ChiaException
     */
    public function getBlockchainState(): array
    {
        return $this->manager->request('get_blockchain_state');
    }

    /**
     * Get the latest block height
     *
     * @return int
     * @throws ChiaException
     */
    public function getLatestBlockHeight(): int
    {
        $state = $this->getBlockchainState();

        return $state['blockchain_state']['peak']['height'];
    }

    /**
     * Last block number
     *
     * @return array
     * @throws ChiaException
     */
    public function getCurrentBlock(): array
    {
        $height = $this->getLatestBlockHeight();

        return $this->getBlockRecordByHeight($height);
    }

    /**
     * Get block record using HashString or blockNumber
     *
     * @param null $block
     * @return array
     * @throws ChiaException
     */
    public function getBlockRecord($block = 'latest'): array
    {
        $block = (is_null($block) ? $this->defaultBlock : $block);

        if ($block === false) {
            throw new ChiaException('No block identifier provided');
        }

        if ($block == 'latest') {
            return $this->getCurrentBlock();
        }

        if (is_string($block) && strlen($block) == 66) {
            return $this->getBlockRecordByHash($block);
        }

        return $this->getBlockRecordByHeight($block);
    }

    /**
     * Get block record block by Hash
     *
     * @param string $hashBlock
     * @return array
     * @throws ChiaException
     */
    public function getBlockRecordByHash(string $hashBlock): array
    {
        return $this->manager->request('get_block_record', [
            'header_hash' => $hashBlock,
        ]);
    }

    /**
     * Get block record by height
     *
     * @param int $blockID
     * @return array
     * @throws ChiaException
     */
    public function getBlockRecordByHeight(int $blockID): array
    {
        if (!is_integer($blockID) || $blockID < 0) {
            throw new ChiaException('Invalid block number provided');
        }

        return $this->manager->request('get_block_record_by_height', [
            'height' => $blockID,
        ]);
    }

    /**
     * Get additions and removals
     *
     * @param null $block
     * @return array
     * @throws ChiaException
     */
    public function getAdditionsAndRemovals($block = null)
    {
        if (is_int($block)) {
            $blockRecord = $this->getBlockRecord($block);
            $block = $blockRecord['block_record']['header_hash'];
        }

        return $this->manager->request('get_additions_and_removals', [
            'header_hash' => $block,
        ]);
    }

    /**
     * Get coin records by puzzle hash
     *
     * @param string $puzzleHash
     * @param int    $startHeight
     * @param int    $endHeight
     * @param bool   $includeSpentCoins
     * @return array
     * @throws ChiaException
     */
    public function getCoinRecordsByPuzzleHash(string $puzzleHash, int $startHeight = 0, int $endHeight = 0, bool $includeSpentCoins = true)
    {
        $data = ['puzzle_hash' => $puzzleHash];
        if ($startHeight) $data['start_height'] = $startHeight;
        if ($endHeight) $data['end_height'] = $endHeight;
        if ($includeSpentCoins) $data['include_spent_coins'] = $includeSpentCoins;

        return $this->manager->request('get_coin_records_by_puzzle_hash', $data);
    }

    /**
     * Get coin records by address
     *
     * @param string $address
     * @param int    $startHeight
     * @param int    $endHeight
     * @param bool   $includeSpentCoins
     * @return array
     * @throws ChiaException
     * @throws ChiaUtilsException
     */
    public function getCoinRecordsByAddress(string $address, int $startHeight = 0, int $endHeight = 0, bool $includeSpentCoins = true)
    {
        $puzzleHash = $this->address2PuzzleHash($address);

        return $this->getCoinRecordsByPuzzleHash($puzzleHash, $startHeight, $endHeight, $includeSpentCoins);
    }

    /**
     * Get coin record by name
     *
     * @param string $name
     * @return array
     * @throws ChiaException
     */
    public function getCoinRecordByName(string $name)
    {
        return $this->manager->request('get_coin_record_by_name', [
            'name' => $name,
        ]);
    }

    /**
     * Push Tx (Broadcast Transaction)
     *
     * @param array $spendBundle
     * @return array
     * @throws ChiaException
     */
    public function pushTx(array $spendBundle): array
    {
        return $this->manager->request('push_tx', [
            'spend_bundle' => $spendBundle,
        ]);
    }

    /**
     * Get mem pool item by tx id
     *
     * @param string $txId
     * @return array
     * @throws ChiaException
     */
    public function getMemPoolItemByTxId($txId): array
    {
        return $this->manager->request('get_mempool_item_by_tx_id', [
            'tx_id' => $txId,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | WALLET API
    |--------------------------------------------------------------------------
    |
    | Here is the wallet api for chia.
    |
    */

    /**
     * Get wallets
     *
     * @return array
     * @throws ChiaException
     */
    public function getWallets(): array
    {
        return $this->manager->request('get_wallets', [], ChiaManager::WALLET_SERVER);
    }

    /**
     * Get wallet balance
     *
     * @return array
     * @throws ChiaException
     */
    public function getWalletBalance(): array
    {
        return $this->manager->request('get_wallet_balance', [
            'wallet_id' => '1',
        ], ChiaManager::WALLET_SERVER);
    }

    /**
     * Get transaction by transaction id
     *
     * @param string $transactionID
     * @return array
     * @throws ChiaException
     */
    public function getTransaction(string $transactionID): array
    {
        return $this->manager->request('get_transaction', [
            'wallet_id'      => '1',
            'transaction_id' => $transactionID,
        ], ChiaManager::WALLET_SERVER);
    }

    /**
     * Get transactions
     *
     * @return array
     * @throws ChiaException
     */
    public function getTransactions(): array
    {
        return $this->manager->request('get_transactions', [
            'wallet_id' => '1',
        ], ChiaManager::WALLET_SERVER);
    }

    /**
     * Get transaction count
     *
     * @return array
     * @throws ChiaException
     */
    public function getTransactionCount(): array
    {
        return $this->manager->request('get_transaction_count', [
            'wallet_id' => '1',
        ], ChiaManager::WALLET_SERVER);
    }

    /**
     * Get next address
     *
     * @param bool $newAddress
     * @return array
     * @throws ChiaException
     */
    public function getNextAddress(bool $newAddress = true): array
    {
        return $this->manager->request('get_next_address', [
            'wallet_id'   => '1',
            'new_address' => $newAddress,
        ], ChiaManager::WALLET_SERVER);
    }

    /**
     * Create signed transaction
     *
     * @param int    $amount
     * @param string $puzzleHash
     * @return array
     * @throws ChiaException
     */
    public function createSignedTransaction(int $amount, string $puzzleHash): array
    {
        return $this->manager->request('create_signed_transaction', [
            'additions' => [
                ['amount' => $amount, 'puzzle_hash' => $puzzleHash],
            ],
        ], ChiaManager::WALLET_SERVER);
    }

    /**
     * Send transaction to Blockchain
     *
     * @param string $to
     * @param float  $amount
     * @return array
     * @throws ChiaException
     * @throws ChiaUtilsException
     */
    public function sendTransaction(string $to, float $amount): array
    {
        if (!is_float($amount) || $amount < 0) {
            throw new ChiaException('Invalid amount provided');
        }

        $amount = $this->toChia($amount);
        $to = $this->address2PuzzleHash($to);

        $signedTransaction = $this->createSignedTransaction($amount, $to);

        $this->pushTx($signedTransaction['signed_tx']['spend_bundle']);

        return $signedTransaction;
    }

    /**
     * Send to multiple addresses or puzzle hashes in a single transaction
     *
     * @param string $walletId The wallet ID to use
     * @param array $additions Array of transaction additions ($addition = [ 'amount' => $mojos, 'puzzle_hash' => $ph, 'memos' => [ 'Transaction memo' ] ])
     * @param int $fee Transaction fee to send in mojos
     * @return array A wallet API response
     * @throws ChiaException
     * @throws ChiaUtilsException
     */
    public function sendTransactionMulti(string $walletId, array $additions, $fee = 0)
    {
        $chiaUtils = new ChiaUtils();
        $additionsHex = [];
        
        if (empty($additions)) {
            throw new \InvalidArgumentException("additions cannot be empty in sendTransactionMulti");
        }

        foreach($additions as $idx => $ad) {
            if (!is_array($ad)) {
                throw new \InvalidArgumentException(
                    sprintf("addition at index $idx must be array, %s given", gettype($ad))
                );
            } elseif (!isset($ad['amount']) || !is_int($ad['amount'])) {
                throw new \InvalidArgumentException("missing or non-numeric amount in addition at key $idx");
            } elseif (!isset($ad['address']) && !isset($address['puzzle_hash'])) {
                throw new \InvalidArgumentException("address or puzzle_hash missing in addition at key $idx");
            } elseif (isset($ad['puzzle_hash']) && !is_array($ad['puzzle_hash'])) {
                throw new \InvalidArgumentException("puzzle_hash must be array in addition at key $idx");
            }
            
            if (!empty($ad['address'])) {
                $ad['puzzle_hash'] = $chiaUtils->decodePuzzleHash($ad['address']);
            }
            
            $addition = [
                'amount' => $ad['amount'],
                'puzzle_hash' => $chiaUtils->bytesToHex($ad['puzzle_hash']),
            ];
            if (array_key_exists('memos', $ad)) {
                $addition['memos'] = $ad['memos'];
            }

            $additionsHex[] = $addition;
        }

        return $this->manager->request('send_transaction_multi', [
            'wallet_id' => $walletId,
            'additions' => $additionsHex,
            'fee'       => $fee,
        ], ChiaManager::WALLET_SERVER);
    }
    
    /**
     * Get coin info
     *
     * @param string $parentCoinInfo
     * @param string $puzzleHash
     * @param int    $amount
     * @return false|string
     */
    public function getCoinInfo(string $parentCoinInfo, string $puzzleHash, int $amount)
    {
        $chiaUtils = new ChiaUtils();

        return $chiaUtils->getCoinInfo($parentCoinInfo, $puzzleHash, $this->fromChia($amount));
    }
}
