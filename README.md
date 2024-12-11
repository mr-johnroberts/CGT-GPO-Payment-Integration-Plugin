# CGT-GPO-Payment-Integration-Plugin
CGT WP GPO Payment Integration is a custom WordPress-Woocommerce Plugin that enables seamless integration with the GPO Payment Portal, allowing clients to process transactions efficiently and securely.

# **Technology Stack:** 
JavaScript, CSS3, HTML5, PHP, WordPress, MySQL, AWS, and WooCommerce REST API.

# **Purpose:** 
Enables seamless integration with the GPO Payment Portal, allowing clients to process transactions efficiently and securely.

# **Functionality:**
- Developed a callback URL to handle allowed parameters from the GPO payment portal, generating a unique token ID tied to each transaction.
- Redirects users to the external GPO payment window using the webframe URL appended with the generated token ID for each transaction.
- Processes transaction statuses (ACCEPTED or REJECTED) from GPO portal parameters and auto-triggers corresponding WooCommerce actions:
- Marks transactions as completed for successful (ACCEPTED) payments.
- Flags transactions as failed (REJECTED) and redirects users to a failure notice page.
- For successful transactions, redirects users to a thank you page displaying order and transaction details.

# **Integration:** 
 Connects seamlessly with the WooCommerce REST API to retrieve and display product information in real-time.

# **Impact:** 
Enhanced payment processing for an Angola-based client, meeting operational objectives and providing a seamless user experience.
