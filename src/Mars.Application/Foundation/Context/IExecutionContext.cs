namespace Mars.Application.Foundation.Context;

public interface IExecutionContext
{
    Guid ActorId { get; }

    Guid CompanyId { get; }

    Guid? BranchId { get; }

    CorrelationId CorrelationId { get; }
}
