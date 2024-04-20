'use strict';
class Blockonomics {
    constructor({ checkout_id = 'blockonomics_checkout' } = {}) {
        // User Params
        this.checkout_id = checkout_id;

        // Initialise
        this.init();
    }

    usdt = {
        address: '0xdAC17F958D2ee523a2206206994597C13D831ec7',
        abi: [
          "function name() view returns (string)",
          "function symbol() view returns (string)",
          "function gimmeSome() external",
          "function balanceOf(address _owner) public view returns (uint256 balance)",
          "function transfer(address _to, uint256 _value) public returns (bool success)",
        ],
    }

    provider = null;
    connectedAccount = null;

    init() {
        this.container = document.getElementById(this.checkout_id);
        if (!this.container) {
            throw Error(
                `Blockonomics Initialisation Error: Container #${this.checkout_id} was not found!`
            );
        }

        // Load data attributes
        // This assumes a constant/var `blockonomics_data` is defined before the script is called.
        try {
            this.data = JSON.parse(blockonomics_data);
        } catch (e) {
            if (e.toString().includes('ReferenceError')) {
                throw Error(
                    `Blockonomics Initialisation Error: Data Object was not found in Window. Please set blockonomics_data variable.`
                );
            }
            throw Error(
                `Blockonomics Initialisation Error: Data Object is not a valid JSON.`
            );
        }

        this.create_bindings();

        this._spinner_wrapper.style.display = 'none';
        this._order_panel.style.display = 'block';

        // Hide Display Error
        this._display_error_wrapper.style.display = 'none';
        this.wallet();
    }

    async wallet() {
        const walletSetupTable = document.getElementById('wallet-setup-table');
        const connectBtn = document.getElementById('connect-wallet');
        const transferForm = document.getElementById('transferForm');

        if (this.data?.network_type === 'Test') {
            this.usdt.address = '0x419Fe9f14Ff3aA22e46ff1d03a73EdF3b70A62ED';
        }

        this.provider = new ethers.providers.Web3Provider(window.ethereum, "any");

        this.provider.provider.on('accountsChanged', async () => {
            this.checkConnection();
        });

        this.provider.on("network", (newNetwork, oldNetwork) => {
            // When a Provider makes its initial connection, it emits a "network"
            // event with a null oldNetwork along with the newNetwork. So, if the
            // oldNetwork exists, it represents a changing network
            if (oldNetwork) {
                window.location.reload();
            }
        });

        connectBtn.addEventListener('click', async () => {
            await this.provider.send("eth_requestAccounts", []);
            const signer = this.provider.getSigner();

            this.connectedAccount = await signer.getAddress();

            const network = await this.provider.getNetwork();
            const desiredNetworkId = this.data.network_type === "Test" ? 11155111 : 1;

            if(network.chainId === desiredNetworkId) {
                console.log("Connected to the correct network");
            } else {
                console.error("Connected to the wrong network");
            }
        });

        transferForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.transferusdt();
        });
        

        this.checkConnection();
    }

    async checkConnection() {
        const walletSetupTable = document.getElementById('wallet-setup-table');
        const walletInfoTable = document.getElementById('wallet-info');

        try {
            const signer = this.provider.getSigner();
            const userAddress = await signer.getAddress();

            const network = await this.provider.getNetwork();
            const desiredNetworkId = this.data.network_type === "Test" ? 11155111 : 1;

            document.getElementById("connectResponse").style.display = "none";

            if(network.chainId !== desiredNetworkId) {
                const response = `Please change the wallet network before connecting the wallet`;
                document.getElementById("connectResponse").innerText = response;
                document.getElementById("connectResponse").style.display = "block";
                return;
            }

            if (userAddress) {
                walletSetupTable.style.display = 'none';
                walletInfoTable.style.display = 'table';

                document.getElementById("userAddress").innerText = userAddress;

                const usdtBalance = await this.getBalance(userAddress);
                document.getElementById("userAmount").innerText = `${this.data.order_amount} USDT`;

                const amount = ethers.utils.parseUnits(this.data.order_amount, 6);

                if (usdtBalance.lt(amount)) {
                    let amountFormatted = ethers.utils.formatUnits(amount, 6);
                    let balanceFormatted = ethers.utils.formatUnits(usdtBalance, 6);
                    console.error(
                    `Insufficient balance receiver send ${amountFormatted} (You have ${balanceFormatted})`
                    );
                
                    const response = `Insufficient balance receiver send ${amountFormatted} (You have ${balanceFormatted})`;
                    document.getElementById("transferResponse").innerText = response;
                    document.getElementById("transferResponse").style.display = "block";
                }
            } else {
                walletSetupTable.style.display = 'table';
                walletInfoTable.style.display = 'none';
            }
        } catch (e) {
            console.log(e);
            walletSetupTable.style.display = 'table';
            walletInfoTable.style.display = 'none';
        }
    }

    async getBalance(wallet) {
        const signer = this.provider.getSigner();
        const usdtContract = new ethers.Contract(this.usdt.address, this.usdt.abi, signer);
        const balance = await usdtContract.balanceOf(wallet);
        return balance;
        // return ethers.utils.formatUnits(balance, 6);
    }

    async transferusdt() {
        let receiver = this.data.usdt_receivers_address;

        const signer = this.provider.getSigner();
        const usdtContract = new ethers.Contract(this.usdt.address, this.usdt.abi, signer);
        let response = '';
        let amount = this.data.order_amount;

        try {
            receiver = ethers.utils.getAddress(receiver);
        } catch {
            response = `Invalid address: ${receiver}`;
            document.getElementById("transferResponse").innerText = response;
            document.getElementById("transferResponse").style.display = "block";
            return;
        }

        try {
            amount = ethers.utils.parseUnits(amount, 6);
            if (amount.isNegative()) {
              throw new Error();
            }
        } catch {
            console.error(`Invalid amount: ${amount}`);
            response = `Invalid amount: ${amount}`;
            document.getElementById("transferResponse").innerText = response;
            document.getElementById("transferResponse").style.display = "block";
            return;
        }

        const userAddress = await signer.getAddress();
        const balance = await usdtContract.balanceOf(userAddress);

        if (balance.lt(amount)) {
            let amountFormatted = ethers.utils.formatUnits(amount, 6);
            let balanceFormatted = ethers.utils.formatUnits(balance, 6);
            console.error(
              `Insufficient balance receiver send ${amountFormatted} (You have ${balanceFormatted})`
            );
        
            response = `Insufficient balance receiver send ${amountFormatted} (You have ${balanceFormatted})`;
            document.getElementById("transferResponse").innerText = response;
            document.getElementById("transferResponse").style.display = "block";
            return;
        }

        let amountFormatted = ethers.utils.formatUnits(amount, 6);

        response = `Transferring ${amountFormatted} usdt receiver ${receiver.slice(
            0,
            6
          )}...`;

        document.getElementById("transferResponse").innerText = response;
        document.getElementById("transferResponse").style.display = "block";

        const gasPrice = await this.provider.getGasPrice();
    
        const tx = await usdtContract.transfer(receiver, amount, { gasPrice });
        document.getElementById(
        "transferResponse"
        ).innerText += `Transaction hash: ${tx.hash}`;

        const result = {
            txn: tx.hash,
            crypto: 'usdt'
        };

        console.log({tx});

        this.redirect_to_finish_order(result);
    }

    async disconnect() {
        await window.ethereum.request({
            method: "eth_requestAccounts",
            params: [{eth_accounts: {}}]
        })
    }

    create_bindings() {
        this._spinner_wrapper = this.container.querySelector(
            '.bnomics-spinner-wrapper'
        );

        this._order_panel = this.container.querySelector(
            '.bnomics-order-panel'
        );
        
        this._display_error_wrapper = this.container.querySelector(
            '.bnomics-display-error'
        );
    }

    redirect_to_finish_order(params = {}) {
        let url = this.data.finish_order_url;

        let queryParams = new URLSearchParams();
        for (const key in params) {
            if (params.hasOwnProperty(key)) {
                queryParams.append(key, params[key]);
            }
        }

        if (Array.from(queryParams).length > 0) {
            url += '&' + queryParams.toString();
        }

        window.location.href = url;
    }
}

// Automatically trigger only after DOM is loaded
new Blockonomics();