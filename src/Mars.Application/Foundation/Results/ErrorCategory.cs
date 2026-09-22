namespace Mars.Application.Foundation.Results;

public enum ErrorCategory
{
    Validation = 1,
    Authentication = 2,
    Authorization = 3,
    NotFound = 4,
    Conflict = 5,
    Concurrency = 6,
    BusinessRule = 7,
    RateLimit = 8,
    Infrastructure = 9,
    ProviderFailure = 10
}
