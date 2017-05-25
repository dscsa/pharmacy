To Expose Server Via Comcast
1-800-391-3000 ask for Tech Support

1. Run node server on desired port
2. Turn off Control Panel > Windows Firewall
3. Set IP info on networking card
  - Control Planel > Network and Sharing Center > Change Adapter Setting (left menu) > Right Click Ethernet Properties > IPV4 Properties
  - IP address 96.67.225.25 (this is one less than whatismyip.com provided ".26")
  - Subnet Mask 255.255.255.252
  - Default Gateway 96.67.225.26 (this is what whatismyip.com provided)
  - Preferred DNS 75.75.75.75
  - Alternate DNS 75.75.76.76
4. Run ipconfig /all to double check setting were saved
5. Goto gateway 96.67.225.26
  - Login with cusadmin, highspeed
  - Goto Advanced > Port Forwarding > Enable > Add Service > Custom > Add Server & Port
  - Goto Advanced > Port Management > Check box to disable all rules and allow inbound traffic

  Installing SSH on windows


  Installing Git on windows

  Setup User on MSSQL
