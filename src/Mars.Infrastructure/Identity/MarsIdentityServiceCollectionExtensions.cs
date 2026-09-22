using Mars.Infrastructure.Persistence;
using Microsoft.AspNetCore.Identity;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;
using OpenIddict.EntityFrameworkCore;

namespace Mars.Infrastructure.Identity;

public static class MarsIdentityServiceCollectionExtensions
{
    public static IServiceCollection AddMarsIdentityPersistence(
        this IServiceCollection services,
        string connectionString)
    {
        ArgumentNullException.ThrowIfNull(services);

        services.AddDbContext<MarsDbContext>(
            options => MarsDbContextOptions.Configure(options, connectionString));

        services
            .AddIdentityCore<MarsIdentityUser>(options =>
            {
                options.User.RequireUniqueEmail = true;
            })
            .AddEntityFrameworkStores<MarsDbContext>();

        services
            .AddOpenIddict()
            .AddCore(options =>
            {
                options
                    .UseEntityFrameworkCore()
                    .UseDbContext<MarsDbContext>();
            });

        return services;
    }
}
