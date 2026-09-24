using Mars.Domain.Sales;

namespace Mars.Api.Sales;

public sealed record CreateSalesProformaRequest(
    string Number,
    SalesProformaSourceMode SourceMode,
    Guid SourceDocumentPublicId);

public sealed record CancelSalesProformaRequest(
    long Version,
    string Reason);
