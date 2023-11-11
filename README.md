# WooCommerce FreshBooks Integration Plugin #

As the BeycanPress team, we decided to use FreshBooks in our company, but since we couldn't find any plugin, we developed a plugin ourselves and decided to publish it as open source. We welcome contributions from all users who use FreshBooks with WooCommerce.

This extension has been developed with the [BeycanPress/FreshBooks](https://github.com/BeycanPress/freshbooks) package, that is, the PHP SDK.

## Installation ##
1. Download the plugin zip file.
2. Go to the WordPress admin area and click on **Plugins** in the left sidebar menu.
3. Click on the **Add New** button at the top of the page.
4. Click on the **Upload Plugin** button at the top of the page.
5. Select the zip file from your computer and click the **Install Now** button.
6. Activate the plugin.

## Configuration ##

### Create FreshBooks APP 

1. Go to the [FreshBooks Developer Portal](https://my.freshbooks.com/#/developer) and click on the **Create an App** button.

![Screenshot 1](https://i.ibb.co/7v5nF5r/Screenshot-1.png)

![Screenshot 2](https://i.ibb.co/tJP5Vmp/Screenshot-2.png)

### Enter Your APP Information

![Screenshot 3](https://i.ibb.co/99Bdrfk/Screenshot-3.png)

### Enter Your APP Scope

When selecting the scopes, you should definitely choose the scopes indicated in the image and text below.

`user:profile:read`
`user:invoices:read`
`user:invoices:write`
`user:clients:read`
`user:clients:write`
`user:payments:read`
`user:payments:write`

![Screenshot 4](https://i.ibb.co/vjpSFQZ/Screenshot-4.png)

### Enter Your APP Redirect URI

This is important because if there is an error here, the access section will not work. 

Here you definitely need to enter the API URL of our plugin after your domaniniz as shown below and in the picture.

`https://test-domain.com/wp-json/wcfb/get-access-token`

### Save Your APP

![Screenshot 5](https://i.ibb.co/rsxTx8f/Screenshot-5.png)

### Enter Your APP and Copy Your APP ID and APP Secret

![Screenshot 6](https://i.ibb.co/S5bL6Fx/Screenshot-6.png)

![Screenshot 7](https://i.ibb.co/2nVdFH8/Screenshot-7.png)

### In WP-Admin, click on the WC FreshBooks Settings Menu

Enter your copied APP ID and APP Secret code in the relevant fields.

After entering your APP ID and APP Secret code, click save above and the page will refresh.

Then press the "connect" button.

![Screenshot 8](https://i.ibb.co/g47ZVwQ/Screenshot-8.png)

### After Clicking the Connect Button

You will be redirected to the FreshBooks login page. After logging in, you will be asked to allow the application. Click the "Allow" button.

![Screenshot 9](https://i.ibb.co/pn6Y5Zn/Screenshot-9.png)

### After All These Steps

Here you will see the business memberships or accounts in your FreshBooks account. Select the one that suits you and click save again.

![Screenshot 10](https://i.ibb.co/tbBYZNY/Screenshot-10.png)

### Settings

#### Create invoice

If you want to create an invoice automatically when the order is completed, you can activate this option.

#### Send to email

If you want to send the invoice to the customer's email address, you can activate this option.

#### Excluded payment methods

If you want to exclude some payment methods, you can select them here. The invoice creation process will not work when payment is made with the methods you have selected here.

![Screenshot 11](https://i.ibb.co/7v5nF5r/Screenshot-11.png)