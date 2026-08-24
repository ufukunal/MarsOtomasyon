<?php

namespace App\Foundation\Providers;

enum ProviderFamily: string
{
    case Marketplace = 'marketplace';
    case Shipping = 'shipping';
    case Payment = 'payment';
    case EDocument = 'e_document';
    case CommunicationSms = 'communication_sms';
    case CommunicationEmail = 'communication_email';
    case CommunicationWhatsApp = 'communication_whatsapp';
    case ExchangeRate = 'exchange_rate';
    case Storage = 'storage';
    case OcrDocumentExtraction = 'ocr_document_extraction';
    case AiAssistant = 'ai_assistant';
    case AccountingExport = 'accounting_export';
    case FeedDiscovery = 'feed_discovery';
}
