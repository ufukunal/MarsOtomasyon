namespace Mars.Application.Foundation.Context;

public sealed class ExecutionContext : IExecutionContext
{
    public ExecutionContext(
        Guid actorId,
        Guid companyId,
        Guid? branchId,
        CorrelationId correlationId)
    {
        if (actorId == Guid.Empty)
        {
            throw new ArgumentException("Actor id is required.", nameof(actorId));
        }

        if (companyId == Guid.Empty)
        {
            throw new ArgumentException("Company id is required.", nameof(companyId));
        }

        if (branchId == Guid.Empty)
        {
            throw new ArgumentException("Branch id must be null or a non-empty UUID.", nameof(branchId));
        }

        ActorId = actorId;
        CompanyId = companyId;
        BranchId = branchId;
        CorrelationId = correlationId;
    }

    public Guid ActorId { get; }

    public Guid CompanyId { get; }

    public Guid? BranchId { get; }

    public CorrelationId CorrelationId { get; }
}
