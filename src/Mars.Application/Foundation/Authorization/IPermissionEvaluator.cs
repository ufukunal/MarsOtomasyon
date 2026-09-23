namespace Mars.Application.Foundation.Authorization;

public interface IPermissionEvaluator
{
    Task<bool> IsGrantedAsync(
        Guid actorId,
        Guid companyId,
        string permissionCode,
        CancellationToken cancellationToken);
}
