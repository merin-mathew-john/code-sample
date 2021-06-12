<?php

namespace Drupal\ige_zendesk;

/**
 * Template for email to attorneys.
 */
class ZendeskEmailProcessor {

  /**
   * Template of email for English attorneys for trademark search.
   *
   * @param array $data
   *   Data from zendesk ticket.
   *
   * @return array
   *   Email template.
   */
  public function getEnglishAttorneyOrderEmail($data) {
    $mail_body = t("Dear @attorney_firstname,</br></br>

    Please proceed with the following Trademark Search:<br>

    </br>

    Language: @language<br></br>

        <b>Trademark Details</b></br></br>

    Trademark name: @trademarktext</br>
    Trademark type: @trademarktype</br>
    Trademark logo:
    ![**@image_text**] (@image_link)</br>

    Class(es): @classes</br>
    Description: @description</br></br>

       <b> Notes to Attorney (if any)</b>
    :@attorney_notes</br></br>

        <b>Order Details</b></br></br>

    Order number: @order_number</br>
    Country: @country</br>
    Language: @language</br>


   </br>

    Please kindly confirm receipt of this order and let us know if you need anything to proceed.</br></br>

    I look forward to hearing from you,</br></br>

    Best regards,
    ", [
      '@attorney_firstname' => $data['requester_name'],
      '@language' => $data['language'],
      '@trademarktext' => $data['trademark_name'],
      '@trademarktype' => $data['trademark_type'],
      '@image_link' => $data['image_link'],
      '@classes' => $data['classes'],
      '@attorney_notes' => $data['notes'],
      '@order_number' => $data['order_number'],
      '@country' => $data['country'],
      '@image_text' => $data['image_text'],
      '@description' => $data['description'],
    ]);
    return $mail_body;
  }

  /**
   * Template of email for English attorneys for trademark application.
   *
   * @param array $data
   *   Data from zendesk ticket.
   *
   * @return array
   *   Email template.
   */
  public function getApplicationAttorneyOrderEmail($data) {
    $mail_body = t("Dear @attorney_firstname,</br></br>

    Please proceed to file a Trademark Application in @country with the following details:<br><br>


    <b>Trademark Details</b></br></br>

Trademark name: @trademarktext<br>
Trademark type: @trademarktype<br>
Trademark logo:
![**@image_text**] (@image_link)</br>
Claim color as a feature of the mark: [Yes/No]<br>

Class(es):@classes</br>
Description: @description</br><br>

    <b>Notes to Attorney (if any)</b><br><br>

Priority claim:@priority</br>
Country: @country</br>
Filing date:@filing_date<br>
Filing number:@filing_number<br>
Priority document: [**click here to download**] (XXPaste_Link_HereXX)<br><br>


    <b>Owner Details</b><br><br>

Owner type: **[Individual or company]**<br>

First name: **[Owner’s first name]** (only if individual)<br>
Last name: **[Owner’s last name]** (only if individual)<br>

Company name: **[Owner name]** (only if company)<br>

Address: **[Owner’s address]**<br>
City: **[Owner’s City]**<br>
Postcode: **[Owner’s postal code]**<br>
Country: **[Owner’s country]**<br>

Power of attorney: [**Click here to download POA**] ([POA link]) OR **[The POA has already been sent]** OR **[Please proceed with filing, the POA will be sent later.]**<br><br>

      <b>Order Details</b><br>

Order number: **[Attorney order code]**<br>
Official fees: **[Trademark application official fee Currency][Trademark application official fee amount]**<br>
Legal fees: **[Application Cost Currency][Application cost amount]**<br><br>

    <b>Notes</b><br><br>

*Please let us know before you proceed to file if:<br>
- the description of goods or services does not correspond to the classes indicated above or is not acceptable to file.<br>
- the Owner details in this order are different from the ones on the Power of Attorney.<br>
- the fees incurred differ in any way from the ones mentioned in Order Details.<br><br>



Please kindly confirm receipt of this order and let us know if you need anything to proceed.<br><br>

I look forward to hearing from you,<br><br>

Best regards,

    ", [
      '@attorney_firstname' => $data['requester_name'],
      '@language' => $data['language'],
      '@trademarktext' => $data['trademark_name'],
      '@trademarktype' => $data['trademark_type'],
      '@image_link' => $data['image_link'],
      '@classes' => $data['classes'],
      '@attorney_notes' => $data['notes'],
      '@order_number' => $data['order_number'],
      '@country' => $data['country'],
      '@image_text' => $data['image_text'],
      '@description' => $data['description'],
      '@priority' => $data['priority'],
      '@filing_date' => $data['filing_date'],
      '@filing_number' => $data['filing_number'],

    ]);
    return $mail_body;
  }

  /**
   * Template of email for attorney for trademark application in USA.
   *
   * @param array $data
   *   Data from zendesk ticket.
   *
   * @return array
   *   Email template.
   */
  public function getUsaApplicationAttorneyOrderEmail($data) {

    $mail_body = t("Dear Jose,<br><br>

Please proceed to file a Trademark Application in the USA with the following details:<br><br>



  <b>Trademark Details</b><br><br>

Trademark name:@trademarktext<br>
Trademark type:@trademarktype<br>
Trademark logo:
![**@image_text**] (@image_link)<br>
Claim color as a feature of the mark: **[Yes/No]<br>

Translations: **[editable text field]<br>
Disclaimers: **[editable text field]<br>

Class(es): @classes<br>
Description: @description<br></br>

   <b>Filing Basis and/or Priority</b><br></br>

Filing basis: **Intent to Use** / **Actual Use** / **Foreign Registration**</br>

XXIf_actual_useXX</br>
Date of first use: **MM/DD/YYYY (mm/dd/yyyy)**<br>
Specimen of use: [**Click here to download**] (XXPaste_Link_HereXX)<br>

XXIf_foreign_registrationXX<br>
Country: **XXCountryXX**<br>
Registration number:@registration_number<br>
Registration date: **XXRegistration_dateXX**<br>
Copy of the certificate: [**Click here to download**] (XXPaste_Link_HereXX)<br>
Translation of the certificate: [**Click here to download**] (XXPaste_Link_HereXX)<br>

Priority claim: **Yes** / **No** XXIf yes, fill in the following:XX<br>
Country: **Country**<br>
Filing date: @filing_date<br>
Filing number:@filing_number<br>
Copy of the application: [**Click here to download**] (XXPaste_Link_HereXX)<br><br>

<b>Owner Details</b><br><br>

Owner type: **[Individual or company]**<br>

First name: **[Owner’s first name]** (only if individual)<br>
Last name: **[Owner’s last name]** (only if individual)<br>

Company name: **[Owner name]** (only if company)<br>

Address: **[Owner’s address]**<br>
City: **[Owner’s City]**<br>
Postcode: **[Owner’s postal code]**<br>
Country: **[Owner’s country]**<br><br>

<b>Order Details</b><br><br>

Order number: **[Attorney order code]**<br>
Official fees: **[Trademark application official fee Currency][Trademark application official fee amount]**<br>
Legal fees: **[Application Cost Currency][Application cost amount]**<br><br>

<b>Notes</b><br><br>

Please let us know before you proceed to file if:<br><br>
- the description of goods or services does not correspond to the classes indicated above or is not acceptable to file.<br>
- the Owner details in this order are different from the ones on the Power of Attorney.<br>
- the fees incurred differ in any way from the ones mentioned in Order Details.<br><br>

To the best of iGERENT's knowledge and belief the applicant is the owner of the trademark sought to be registered and has the right to use the mark in commerce.*<br><br>



Please kindly confirm receipt of this order and let us know if you need anything to proceed.<br><br>

I look forward to hearing from you,<br><br>

Best regards,
 ", [
    '@attorney_firstname' => $data['requester_name'],
    '@language' => $data['language'],
    '@trademarktext' => $data['trademark_name'],
    '@trademarktype' => $data['trademark_type'],
    '@image_link' => $data['image_link'],
    '@classes' => $data['classes'],
    '@attorney_notes' => $data['notes'],
    '@order_number' => $data['order_number'],
    '@country' => $data['country'],
    '@image_text' => $data['image_text'],
    '@description' => $data['description'],
    '@priority' => $data['priority'],
    '@filing_date' => $data['filing_date'],
    '@filing_number' => $data['filing_number'],
    '@registration_number' => $data['registration_number'],
 ]);
    return $mail_body;
  }

  /**
   * Template of client email with quote for trademark search and application.
   *
   * @param array $data
   *   Data from zendesk ticket.
   *
   * @return array
   *   Email template.
   */
  public function getQuoteEmail($data) {
    $mail_body = t("Dear @client_firstname, <br/><br/>

    Quote: <bt/>
    Please download below your quote for trademark registration in the @countries <br/><br/>
    <a href = '@link'>Click here to download quote</a>,<br/>
    <i>The downloaded quote is valid for 14 days from the date of this email</i> <br/>
    <b>Summary</b> for quote for a trademark in @class_number class <br/>
    <i>For detailed fees please see the quote available above</i> <br/>
     - Trademark Search (optional): @study_price <br/>
     - Trademark Application - @application_price <br/>
     <i>The prices for searches are for wordmark only</i> <br/><br/>
     <b>Trademark search discount</b> <br/>
     We are pleased to offer you a discount of 20% off the price for the Trademark search indicated above if you order within the next 10 business days. To take advantage of this offer just send me the trademark name and the classes or products/services for which the trademark should be fixed. <br/><br/>

     <b>Next Step</b><br/>
     If you wish to proceed with any service, please click on the links in the quote or send me the trademark and owner information via Email. Our payment alternatives are Credit Card, Paypal and Bank Transfer in US Dollars.<br/>
     <hr>

     <a href = 'www.igerent.com'>More information about iGERENT and our services</a>
     <hr><br/>

     Please do not hesitate to contact me in case you have any questions. <br/>

    Best regards,<br/>
    @consultant
    ", [
      '@client_firstname' => $data['requester_name'],
      '@link' => $data['quote_link'],
      '@class_number' => $data['class_number'],
      '@study_price' => $data['total_study_price'],
      '@application_price' => $data['total_application_price'],
      '@countries' => $data['countries'],
      '@consultant' => $data['consultant'],
    ]);
    return $mail_body;
  }

}
